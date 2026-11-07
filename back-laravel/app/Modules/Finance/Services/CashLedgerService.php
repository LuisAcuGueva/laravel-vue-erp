<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Data\CashMovementData;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Exceptions\CashSessionException;
use App\Modules\Finance\Exceptions\InsufficientCashException;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\CashTransfer;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class CashLedgerService
{
    public function __construct(
        private readonly CashSessionSummaryService $summaryService,
    ) {}

    public function currentSession(Box $box): ?CashSession
    {
        return $box->openSession()->first();
    }

    public function register(CashMovementData $data): CashMovement
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('CashLedgerService requiere una transacción de base de datos activa.');
        }

        if ((float) $data->amount <= 0) {
            throw new LogicException('El monto del movimiento debe ser mayor a cero.');
        }

        /** @var CashSession|null $session */
        $session = CashSession::where('id', $data->sessionId)->lockForUpdate()->first();

        if ($session === null) {
            throw new CashSessionException("Sesión de caja no encontrada: {$data->sessionId}");
        }

        $sessionStatus = $session->status instanceof CashSessionStatus
            ? $session->status
            : CashSessionStatus::tryFrom((string) $session->status);

        if ($sessionStatus !== CashSessionStatus::Open) {
            throw new CashSessionException('La sesión de caja no está abierta.');
        }

        $direction = $data->direction instanceof MoneyDirection
            ? $data->direction
            : MoneyDirection::from((string) $data->direction);

        $concept = null;
        if ($data->conceptId !== null) {
            $concept = FinanceConcept::find($data->conceptId);
        } elseif ($data->conceptCode !== null) {
            $concept = FinanceConcept::where('code', $data->conceptCode)->first();
        }

        if ($concept === null) {
            throw new LogicException('Concepto de finanzas no encontrado.');
        }

        if (! $concept->direction->allows($direction)) {
            throw new LogicException("El concepto {$concept->code} no permite movimientos de dirección {$direction->value}.");
        }

        $paymentMethod = null;
        if ($data->paymentMethodId !== null) {
            $paymentMethod = PaymentMethod::find($data->paymentMethodId);
        } elseif ($data->paymentMethodCode !== null) {
            $paymentMethod = PaymentMethod::where('code', $data->paymentMethodCode)->first();
        } else {
            $paymentMethod = PaymentMethod::where('code', 'CASH')->first();
        }

        if ($paymentMethod === null) {
            throw new LogicException('Método de pago no encontrado.');
        }

        $affectsCash = $paymentMethod->settlement === Settlement::CashBox;

        if ($direction === MoneyDirection::Out && $affectsCash) {
            $excludeTransferId = null;
            if ($data->source instanceof CashTransfer) {
                $excludeTransferId = (string) $data->source->id;
            }

            $available = (float) $this->summaryService->availableCash($session, $excludeTransferId);
            $amount = (float) $data->amount;

            if ($available - $amount < 0) {
                throw new InsufficientCashException('Efectivo insuficiente en caja.');
            }
        }

        return CashMovement::create([
            'cash_session_id' => $session->id,
            'box_id' => $session->box_id,
            'branch_id' => $session->branch_id,
            'direction' => $direction->value,
            'concept_id' => $concept->id,
            'payment_method_id' => $paymentMethod->id,
            'affects_cash' => $affectsCash,
            'amount' => $data->amount,
            'reference' => $data->reference,
            'description' => $data->description,
            'counterparty_name' => $data->counterpartyName,
            'source_type' => $data->source !== null ? $data->source->getMorphClass() : null,
            'source_id' => $data->source?->getKey(),
            'source_number' => $data->sourceNumber,
            'reverses_id' => null,
            'is_manual' => $data->isManual,
            'created_by' => $data->createdBy ?? auth()->id(),
            'moved_at' => $data->movedAt ?? now(),
        ]);
    }

    public function reverse(CashMovement $movement, Model $source, string $sourceNumber, string $reason): CashMovement
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('CashLedgerService requiere una transacción de base de datos activa.');
        }

        if ($movement->reversedBy()->exists()) {
            throw new CashSessionException('El movimiento ya ha sido revertido.');
        }

        /** @var CashSession|null $session */
        $session = CashSession::where('id', $movement->cash_session_id)->lockForUpdate()->first();

        if ($session === null) {
            throw new CashSessionException('Sesión de caja no encontrada.');
        }

        $sessionStatus = $session->status instanceof CashSessionStatus
            ? $session->status
            : CashSessionStatus::tryFrom((string) $session->status);

        if ($sessionStatus !== CashSessionStatus::Open) {
            throw new CashSessionException('La sesión del movimiento ya está cerrada.');
        }

        $origDirection = $movement->direction instanceof MoneyDirection
            ? $movement->direction
            : MoneyDirection::from((string) $movement->direction);

        $newDirection = $origDirection === MoneyDirection::In
            ? MoneyDirection::Out
            : MoneyDirection::In;

        if ($newDirection === MoneyDirection::Out && $movement->affects_cash) {
            $available = (float) $this->summaryService->availableCash($session);
            $amount = (float) $movement->amount;

            if ($available - $amount < 0) {
                throw new InsufficientCashException('Efectivo insuficiente en caja para revertir el movimiento.');
            }
        }

        return CashMovement::create([
            'cash_session_id' => $session->id,
            'box_id' => $session->box_id,
            'branch_id' => $session->branch_id,
            'direction' => $newDirection->value,
            'concept_id' => $movement->concept_id,
            'payment_method_id' => $movement->payment_method_id,
            'affects_cash' => $movement->affects_cash,
            'amount' => $movement->amount,
            'reference' => $movement->reference,
            'description' => "Anulación: {$reason}",
            'counterparty_name' => $movement->counterparty_name,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'source_number' => $sourceNumber,
            'reverses_id' => $movement->id,
            'is_manual' => $movement->is_manual,
            'created_by' => auth()->id(),
            'moved_at' => now(),
        ]);
    }

    public function registerInformative(CashSession $session, SystemConcept $concept, string $amount, string $description): CashMovement
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('CashLedgerService requiere una transacción de base de datos activa.');
        }

        $conceptModel = $concept->model();
        if ($conceptModel === null) {
            throw new LogicException("Concepto de sistema {$concept->value} no encontrado.");
        }

        $cashMethod = PaymentMethod::where('code', 'CASH')->first();
        if ($cashMethod === null) {
            throw new LogicException('Método de pago CASH no encontrado.');
        }

        $direction = $concept === SystemConcept::CashOverage
            ? MoneyDirection::In
            : MoneyDirection::Out;

        return CashMovement::create([
            'cash_session_id' => $session->id,
            'box_id' => $session->box_id,
            'branch_id' => $session->branch_id,
            'direction' => $direction->value,
            'concept_id' => $conceptModel->id,
            'payment_method_id' => $cashMethod->id,
            'affects_cash' => false,
            'amount' => $amount,
            'reference' => null,
            'description' => $description,
            'counterparty_name' => null,
            'source_type' => $session->getMorphClass(),
            'source_id' => $session->id,
            'source_number' => $session->number,
            'reverses_id' => null,
            'is_manual' => false,
            'created_by' => auth()->id(),
            'moved_at' => now(),
        ]);
    }
}
