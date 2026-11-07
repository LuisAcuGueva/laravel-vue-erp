<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Finance\Services\MoneySettlementService;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Sales\Enums\CollectionStatus;
use App\Modules\Sales\Enums\CreditNoteStatus;
use App\Modules\Sales\Enums\EInvoiceStatus;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Events\SaleCancelled;
use App\Modules\Sales\Models\Sale;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleCancellationService
{
    public function __construct(
        private readonly StockLedgerService $stockLedger,
        private readonly MoneySettlementService $settlement,
        private readonly QuotationWorkflowService $quotationWorkflow,
    ) {}

    /**
     * Anula una venta emitida, revirtiendo movimientos de almacén y cobros de caja/banco.
     *
     * @throws ValidationException
     */
    public function cancel(Sale $sale, string $reason, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $user): Sale {
            /** @var Sale $locked */
            $locked = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== SaleStatus::Issued) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden anular comprobantes en estado emitido.',
                ]);
            }

            if ($locked->einvoice_status === EInvoiceStatus::Accepted) {
                throw ValidationException::withMessages([
                    'status' => 'El comprobante fue aceptado por SUNAT; emita una nota de crédito.',
                ]);
            }

            if ($this->hasCollections($locked)) {
                throw ValidationException::withMessages([
                    'status' => 'El comprobante tiene cobranzas posteriores registradas; anúlelas primero o use una nota de crédito.',
                ]);
            }

            if ($this->hasCreditNotes($locked)) {
                throw ValidationException::withMessages([
                    'status' => 'El comprobante tiene notas de crédito aplicadas.',
                ]);
            }

            // 1. Revertir movimientos de Kardex
            $movements = KardexMovement::query()
                ->where('source_type', $locked->getMorphClass())
                ->where('source_id', $locked->getKey())
                ->whereNull('reverses_id')
                ->whereDoesntHave('reversedBy')
                ->get();

            foreach ($movements as $movement) {
                $this->stockLedger->reverse($movement, $locked, $locked->full_number);
            }

            // 2. Revertir cobros en caja o banco
            foreach ($locked->payments as $payment) {
                if ($payment->status === 'completed') {
                    $this->settlement->unsettle($payment, $locked->full_number, $reason, $user->id);
                    $payment->update(['status' => 'cancelled']);
                }
            }

            // 3. Si procedía de una cotización, revertir su estado a 'accepted'
            if ($locked->quotation_id && $locked->quotation) {
                $this->quotationWorkflow->revertConverted($locked->quotation);
            }

            // 4. Actualizar estado de la venta
            $locked->update([
                'status' => SaleStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            // 5. Despachar evento después del commit
            DB::afterCommit(function () use ($locked): void {
                event(new SaleCancelled($locked));
            });

            return $locked->fresh(['items', 'payments', 'series', 'client', 'seller', 'warehouse', 'currency']) ?? $locked;
        });
    }

    public function hasCollections(Sale $sale): bool
    {
        return $sale->collectionAllocations()
            ->whereHas('collection', fn ($q) => $q->where('status', CollectionStatus::Completed))
            ->exists();
    }

    public function hasCreditNotes(Sale $sale): bool
    {
        return $sale->creditNotes()
            ->where('status', CreditNoteStatus::ISSUED)
            ->exists();
    }
}
