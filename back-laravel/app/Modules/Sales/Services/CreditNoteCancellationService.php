<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Sales\Enums\CreditNoteStatus;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditNoteCancellationService
{
    public function __construct(
        private readonly StockLedgerService $stockLedger,
        private readonly CreditNoteSettlementService $settlementService,
    ) {}

    /**
     * Anula una nota de crédito emitida revirtiendo movimientos de almacén y liquidación financiera.
     *
     * @throws ValidationException
     */
    public function cancel(CreditNote $note, string $reason, User $user): CreditNote
    {
        return DB::transaction(function () use ($note, $reason, $user): CreditNote {
            /** @var CreditNote $locked */
            $locked = CreditNote::whereKey($note->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== CreditNoteStatus::ISSUED) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden anular notas de crédito en estado emitida.',
                ]);
            }

            if ($locked->einvoice_status === 'accepted') {
                throw ValidationException::withMessages([
                    'status' => 'No se puede anular una nota de crédito que ya fue aceptada por SUNAT.',
                ]);
            }

            // 1. Revertir movimientos de inventario en kardex
            $kardexMovements = $locked->kardexMovements()->whereNull('reverses_id')->whereDoesntHave('reversedBy')->get();
            foreach ($kardexMovements as $km) {
                $this->stockLedger->reverse($km, $locked, $locked->full_number);
            }

            // 2. Revertir liquidación financiera (reembolso y cuotas)
            $this->settlementService->revert($locked, $user);

            // 3. Marcar nota como anulada
            $locked->update([
                'status' => CreditNoteStatus::CANCELLED,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => trim($reason),
            ]);

            return $locked;
        });
    }
}
