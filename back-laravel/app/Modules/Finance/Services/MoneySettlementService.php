<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Data\BankMovementData;
use App\Modules\Finance\Data\CashMovementData;
use App\Modules\Finance\Data\SettlementData;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Punto único para que los documentos que mueven dinero (pagos a proveedores,
 * cobros, reembolsos) registren o reviertan su movimiento en caja o en banco.
 */
class MoneySettlementService
{
    public function __construct(
        private readonly CashLedgerService $cashLedger,
        private readonly BankLedgerService $bankLedger,
        private readonly BoxAccessService $boxAccessService,
    ) {}

    /**
     * Registra el movimiento del documento en el ledger que corresponde al método de pago.
     *
     * @throws FinanceRuleException
     */
    public function settle(SettlementData $data): CashMovement|BankMovement
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('MoneySettlementService requiere una transacción de base de datos activa.');
        }

        if ((float) $data->amount <= 0) {
            throw new FinanceRuleException('El monto debe ser mayor a cero.', 'amount');
        }

        /** @var PaymentMethod|null $method */
        $method = PaymentMethod::with('bankAccount')->find($data->paymentMethodId);

        if ($method === null || ! $method->is_active) {
            throw new FinanceRuleException('El método de pago no existe o no está activo.', 'payment_method_id');
        }

        if ($method->requires_reference && trim((string) $data->reference) === '') {
            throw new FinanceRuleException("El método {$method->name} requiere un número de referencia.", 'reference');
        }

        $conceptCode = $data->concept instanceof SystemConcept ? $data->concept->value : $data->concept;

        if ($conceptCode === null && $data->conceptId === null) {
            throw new LogicException('La liquidación requiere un concepto de finanzas.');
        }

        return $method->settlement === Settlement::CashBox
            ? $this->settleInCash($data, $method, $conceptCode)
            : $this->settleInBank($data, $method, $conceptCode);
    }

    /**
     * Revierte todos los movimientos vigentes de caja y banco originados por el documento.
     * Cada ledger valida que la sesión o el periodo sigan abiertos.
     *
     * @return int cantidad de movimientos revertidos
     */
    public function unsettle(Model $source, string $sourceNumber, string $reason, ?string $createdBy = null): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('MoneySettlementService requiere una transacción de base de datos activa.');
        }

        $reversed = 0;

        $cashMovements = CashMovement::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('reverses_id')
            ->whereDoesntHave('reversedBy')
            ->orderBy('id')
            ->get();

        foreach ($cashMovements as $movement) {
            $this->cashLedger->reverse($movement, $source, $sourceNumber, $reason);
            $reversed++;
        }

        $bankMovements = BankMovement::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('reverses_id')
            ->whereDoesntHave('reversal')
            ->orderBy('id')
            ->get();

        foreach ($bankMovements as $movement) {
            $this->bankLedger->reverse($movement, $source, $sourceNumber, $reason, $createdBy);
            $reversed++;
        }

        return $reversed;
    }

    private function settleInCash(SettlementData $data, PaymentMethod $method, ?string $conceptCode): CashMovement
    {
        if ($data->cashSessionId === null) {
            throw new FinanceRuleException('La sesión de caja es obligatoria para métodos de pago en efectivo.', 'cash_session_id');
        }

        /** @var CashSession|null $session */
        $session = CashSession::with('box')->find($data->cashSessionId);

        if ($session === null || $session->status !== CashSessionStatus::Open) {
            throw new FinanceRuleException('La sesión de caja no existe o no está abierta.', 'cash_session_id');
        }

        if ($data->branchId !== null && $session->branch_id !== $data->branchId) {
            throw new FinanceRuleException('La sesión de caja no pertenece a la sede del documento.', 'cash_session_id');
        }

        if ($data->user !== null && ($session->box === null || ! $this->boxAccessService->canOperate($data->user, $session->box))) {
            throw new AccessDeniedHttpException('No tiene permisos para operar esta caja.');
        }

        $base = Currency::base();

        if ($base === null || $base->id !== $data->currencyId) {
            throw new FinanceRuleException('Los movimientos de caja solo proceden en la moneda base.', 'currency_id');
        }

        return $this->cashLedger->register(new CashMovementData(
            sessionId: $session->id,
            direction: $data->direction,
            conceptId: $data->conceptId,
            conceptCode: $conceptCode,
            paymentMethodId: $method->id,
            amount: $data->amount,
            source: $data->source,
            sourceNumber: $data->sourceNumber,
            reference: $data->reference,
            description: $data->description,
            counterpartyName: $data->counterpartyName,
            createdBy: $data->user?->id,
        ));
    }

    private function settleInBank(SettlementData $data, PaymentMethod $method, ?string $conceptCode): BankMovement
    {
        $account = $method->bankAccount;

        if ($account === null) {
            throw new FinanceRuleException("El método {$method->name} no tiene una cuenta bancaria asociada.", 'payment_method_id');
        }

        if ($account->currency_id !== $data->currencyId) {
            throw new FinanceRuleException('La cuenta bancaria del método de pago no es de la moneda del documento.', 'currency_id');
        }

        return $this->bankLedger->register(new BankMovementData(
            bankAccountId: $account->id,
            direction: $data->direction,
            conceptId: $data->conceptId,
            conceptCode: $conceptCode,
            amount: $data->amount,
            movementDate: $data->movementDate,
            source: $data->source,
            sourceNumber: $data->sourceNumber,
            operationNumber: $data->reference,
            paymentMethodId: $method->id,
            description: $data->description,
            counterpartyName: $data->counterpartyName,
            branchId: $data->branchId,
            createdBy: $data->user?->id,
        ));
    }
}
