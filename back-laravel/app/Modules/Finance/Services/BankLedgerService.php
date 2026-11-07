<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Data\BankMovementData;
use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Exceptions\BankLedgerException;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Models\FinanceConcept;
use App\Support\Pricing\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class BankLedgerService
{
    public function __construct(
        private readonly SystemParameterService $parameterService,
    ) {}

    public function currentPeriod(BankAccount $account): ?BankPeriod
    {
        return $account->openPeriod()->first();
    }

    public function register(BankMovementData $data): BankMovement
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('BankLedgerService requiere una transacción de base de datos activa.');
        }

        if ((float) $data->amount <= 0) {
            throw new LogicException('El monto del movimiento debe ser mayor a cero.');
        }

        /** @var BankAccount|null $account */
        $account = BankAccount::where('id', $data->bankAccountId)->lockForUpdate()->first();

        if ($account === null) {
            throw new BankLedgerException("Cuenta bancaria no encontrada: {$data->bankAccountId}", 404);
        }

        if (! $account->is_active) {
            throw new BankLedgerException('La cuenta bancaria no está activa.', 422);
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

        /** @var BankPeriod|null $period */
        $period = BankPeriod::where('bank_account_id', $account->id)
            ->where('status', BankPeriodStatus::Open)
            ->lockForUpdate()
            ->first();

        if ($period === null) {
            throw new BankLedgerException('La cuenta bancaria no tiene un periodo abierto', 409);
        }

        $movementDate = $data->movementDate !== null
            ? Carbon::parse($data->movementDate)->startOfDay()
            : now()->startOfDay();

        if ($movementDate->lt($period->opened_at->copy()->startOfDay())) {
            throw new BankLedgerException(
                "La fecha del movimiento ({$movementDate->toDateString()}) es anterior a la apertura del periodo bancario vigente ({$period->opened_at->toDateString()}). Verifique la fecha o registre el movimiento con la fecha de apertura del periodo o una posterior.",
                422
            );
        }

        $currentBalance = (string) $account->balance;
        $amountStr = number_format((float) $data->amount, 2, '.', '');

        $newBalance = $direction === MoneyDirection::In
            ? Decimal::add($currentBalance, $amountStr, 2)
            : Decimal::sub($currentBalance, $amountStr, 2);

        if ((float) $newBalance < 0 && ! $this->parameterService->boolean('finance.allow_negative_bank_balance', false)) {
            throw new BankLedgerException('Saldo insuficiente en cuenta bancaria.', 422);
        }

        $movement = BankMovement::create([
            'bank_account_id' => $account->id,
            'bank_period_id' => $period->id,
            'branch_id' => $data->branchId,
            'direction' => $direction,
            'concept_id' => $concept->id,
            'payment_method_id' => $data->paymentMethodId,
            'amount' => $amountStr,
            'balance_after' => $newBalance,
            'operation_number' => $data->operationNumber,
            'movement_date' => $movementDate->toDateString(),
            'description' => $data->description,
            'counterparty_name' => $data->counterpartyName,
            'voucher_path' => $data->voucherPath,
            'source_type' => $data->source?->getMorphClass(),
            'source_id' => $data->source?->getKey(),
            'source_number' => $data->sourceNumber,
            'is_manual' => $data->isManual,
            'created_by' => $data->createdBy,
        ]);

        $account->forceFill(['balance' => $newBalance])->save();

        return $movement;
    }

    public function reverse(
        BankMovement $movement,
        Model $source,
        string $sourceNumber,
        string $reason,
        ?string $createdBy = null
    ): BankMovement {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('BankLedgerService requiere una transacción de base de datos activa.');
        }

        if ($movement->reversal()->exists()) {
            throw new BankLedgerException('El movimiento ya ha sido revertido.', 422);
        }

        $period = $movement->period;
        if ($period->status !== BankPeriodStatus::Open) {
            throw new BankLedgerException('No se puede revertir un movimiento de un periodo bancario cerrado.', 422);
        }

        /** @var BankAccount $account */
        $account = BankAccount::where('id', $movement->bank_account_id)->lockForUpdate()->firstOrFail();

        $newDirection = $movement->direction === MoneyDirection::In
            ? MoneyDirection::Out
            : MoneyDirection::In;

        $currentBalance = (string) $account->balance;
        $amountStr = (string) $movement->amount;

        $newBalance = $newDirection === MoneyDirection::In
            ? Decimal::add($currentBalance, $amountStr, 2)
            : Decimal::sub($currentBalance, $amountStr, 2);

        if ((float) $newBalance < 0 && ! $this->parameterService->boolean('finance.allow_negative_bank_balance', false)) {
            throw new BankLedgerException('Saldo insuficiente en cuenta bancaria para revertir el movimiento.', 422);
        }

        $reversal = BankMovement::create([
            'bank_account_id' => $account->id,
            'bank_period_id' => $period->id,
            'branch_id' => $movement->branch_id,
            'direction' => $newDirection,
            'concept_id' => $movement->concept_id,
            'payment_method_id' => $movement->payment_method_id,
            'amount' => $amountStr,
            'balance_after' => $newBalance,
            'operation_number' => $movement->operation_number,
            'movement_date' => now()->toDateString(),
            'description' => "Reversión: {$reason}",
            'counterparty_name' => $movement->counterparty_name,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'source_number' => $sourceNumber,
            'reverses_id' => $movement->id,
            'is_manual' => false,
            'created_by' => $createdBy,
        ]);

        $account->forceFill(['balance' => $newBalance])->save();

        return $reversal;
    }
}
