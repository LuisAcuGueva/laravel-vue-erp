<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Data\BankMovementData;
use App\Modules\Finance\Data\CashMovementData;
use App\Modules\Finance\Enums\BankOperationStatus;
use App\Modules\Finance\Enums\BankOperationType;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Exceptions\BankLedgerException;
use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankOperation;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BankOperationService
{
    public function __construct(
        private readonly BankLedgerService $bankLedgerService,
        private readonly CashLedgerService $cashLedgerService,
        private readonly CashSessionSummaryService $cashSessionSummaryService,
        private readonly BoxAccessService $boxAccessService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, BankOperation>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = BankOperation::with([
            'bankAccount.bank',
            'bankAccount.currency',
            'targetBankAccount.bank',
            'targetBankAccount.currency',
            'cashSession.box',
            'creator',
            'canceller',
        ]);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['bank_account_id'])) {
            $bankAccountId = (string) $filters['bank_account_id'];
            $query->where(function ($q) use ($bankAccountId) {
                $q->where('bank_account_id', $bankAccountId)
                    ->orWhere('target_bank_account_id', $bankAccountId);
            });
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('operation_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('operation_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function find(string $id): BankOperation
    {
        return BankOperation::with([
            'bankAccount.bank',
            'bankAccount.currency',
            'targetBankAccount.bank',
            'targetBankAccount.currency',
            'cashSession.box',
            'creator',
            'canceller',
            'bankMovements.concept',
            'cashMovements.concept',
        ])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user, ?UploadedFile $voucher = null): BankOperation
    {
        return DB::transaction(function () use ($data, $user, $voucher) {
            $type = $data['type'] instanceof BankOperationType
                ? $data['type']
                : BankOperationType::from((string) $data['type']);

            $number = SequentialCodeGenerator::next('bank_operation', 'BO', 6);
            $voucherPath = null;
            if ($voucher !== null) {
                $stored = $voucher->store('bank-vouchers', 'local');
                if (is_string($stored)) {
                    $voucherPath = $stored;
                }
            }
            $amount = (float) $data['amount'];
            $operationDate = Carbon::parse((string) $data['operation_date']);

            $baseCurrency = Currency::base() ?? Currency::where('is_base', true)->firstOrFail();

            return match ($type) {
                BankOperationType::Deposit => $this->handleDeposit($data, $user, $number, $amount, $operationDate, $voucherPath, $baseCurrency),
                BankOperationType::Withdrawal => $this->handleWithdrawal($data, $user, $number, $amount, $operationDate, $voucherPath, $baseCurrency),
                BankOperationType::Transfer => $this->handleTransfer($data, $user, $number, $amount, $operationDate, $voucherPath),
            };
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleDeposit(
        array $data,
        User $user,
        string $number,
        float $amount,
        Carbon $operationDate,
        ?string $voucherPath,
        Currency $baseCurrency
    ): BankOperation {
        if (empty($data['cash_session_id'])) {
            throw new BankLedgerException('La sesión de caja es obligatoria para depósitos.', 422);
        }

        /** @var CashSession|null $session */
        $session = CashSession::with('box')->where('id', $data['cash_session_id'])->lockForUpdate()->first();
        if ($session === null || $session->status !== CashSessionStatus::Open) {
            throw new BankLedgerException('La sesión de caja no existe o no está abierta.', 422);
        }

        if ($session->box === null) {
            throw new BankLedgerException('La caja de la sesión no fue encontrada.', 422);
        }
        $box = $session->box;

        if (! $this->boxAccessService->canOperate($user, $box)) {
            throw new BankLedgerException('No tiene permisos para operar esta caja.', 403);
        }

        /** @var BankAccount $bankAccount */
        $bankAccount = BankAccount::where('id', $data['bank_account_id'])->lockForUpdate()->firstOrFail();

        if ($bankAccount->currency_id !== $baseCurrency->id) {
            throw new BankLedgerException('Las operaciones de caja solo proceden con cuentas bancarias en la moneda base de la empresa.', 422);
        }

        $available = (float) $this->cashSessionSummaryService->availableCash($session);
        if ($amount > $available) {
            throw new BankLedgerException('Efectivo insuficiente en caja para realizar el depósito.', 422);
        }

        /** @var BankOperation $operation */
        $operation = BankOperation::create([
            'number' => $number,
            'type' => BankOperationType::Deposit,
            'status' => BankOperationStatus::Completed,
            'bank_account_id' => $bankAccount->id,
            'cash_session_id' => $session->id,
            'amount' => number_format($amount, 2, '.', ''),
            'operation_number' => ! empty($data['operation_number']) ? trim((string) $data['operation_number']) : null,
            'operation_date' => $operationDate->toDateString(),
            'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : null,
            'voucher_path' => $voucherPath,
            'created_by' => $user->id,
        ]);

        $this->cashLedgerService->register(new CashMovementData(
            sessionId: $session->id,
            direction: MoneyDirection::Out,
            conceptCode: SystemConcept::BankDeposit->value,
            amount: $amount,
            source: $operation,
            sourceNumber: $operation->number,
            reference: $operation->operation_number,
            description: "Depósito en {$bankAccount->alias} ({$operation->number})",
            createdBy: $user->id,
        ));

        $this->bankLedgerService->register(new BankMovementData(
            bankAccountId: $bankAccount->id,
            direction: MoneyDirection::In,
            conceptCode: SystemConcept::BankDeposit->value,
            amount: $amount,
            movementDate: $operationDate,
            source: $operation,
            sourceNumber: $operation->number,
            operationNumber: $operation->operation_number,
            description: "Depósito desde caja {$box->name} ({$operation->number})",
            voucherPath: $voucherPath,
            branchId: $session->branch_id,
            createdBy: $user->id,
        ));

        return $operation;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleWithdrawal(
        array $data,
        User $user,
        string $number,
        float $amount,
        Carbon $operationDate,
        ?string $voucherPath,
        Currency $baseCurrency
    ): BankOperation {
        if (empty($data['cash_session_id'])) {
            throw new BankLedgerException('La sesión de caja es obligatoria para retiros.', 422);
        }

        /** @var CashSession|null $session */
        $session = CashSession::with('box')->where('id', $data['cash_session_id'])->lockForUpdate()->first();
        if ($session === null || $session->status !== CashSessionStatus::Open) {
            throw new BankLedgerException('La sesión de caja no existe o no está abierta.', 422);
        }

        if ($session->box === null) {
            throw new BankLedgerException('La caja de la sesión no fue encontrada.', 422);
        }
        $box = $session->box;

        if (! $this->boxAccessService->canOperate($user, $box)) {
            throw new BankLedgerException('No tiene permisos para operar esta caja.', 403);
        }

        /** @var BankAccount $bankAccount */
        $bankAccount = BankAccount::where('id', $data['bank_account_id'])->lockForUpdate()->firstOrFail();

        if ($bankAccount->currency_id !== $baseCurrency->id) {
            throw new BankLedgerException('Las operaciones de caja solo proceden con cuentas bancarias en la moneda base de la empresa.', 422);
        }

        /** @var BankOperation $operation */
        $operation = BankOperation::create([
            'number' => $number,
            'type' => BankOperationType::Withdrawal,
            'status' => BankOperationStatus::Completed,
            'bank_account_id' => $bankAccount->id,
            'cash_session_id' => $session->id,
            'amount' => number_format($amount, 2, '.', ''),
            'operation_number' => ! empty($data['operation_number']) ? trim((string) $data['operation_number']) : null,
            'operation_date' => $operationDate->toDateString(),
            'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : null,
            'voucher_path' => $voucherPath,
            'created_by' => $user->id,
        ]);

        $this->bankLedgerService->register(new BankMovementData(
            bankAccountId: $bankAccount->id,
            direction: MoneyDirection::Out,
            conceptCode: SystemConcept::BankWithdrawal->value,
            amount: $amount,
            movementDate: $operationDate,
            source: $operation,
            sourceNumber: $operation->number,
            operationNumber: $operation->operation_number,
            description: "Retiro para caja {$box->name} ({$operation->number})",
            voucherPath: $voucherPath,
            branchId: $session->branch_id,
            createdBy: $user->id,
        ));

        $this->cashLedgerService->register(new CashMovementData(
            sessionId: $session->id,
            direction: MoneyDirection::In,
            conceptCode: SystemConcept::BankWithdrawal->value,
            amount: $amount,
            source: $operation,
            sourceNumber: $operation->number,
            reference: $operation->operation_number,
            description: "Retiro desde {$bankAccount->alias} ({$operation->number})",
            createdBy: $user->id,
        ));

        return $operation;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleTransfer(
        array $data,
        User $user,
        string $number,
        float $amount,
        Carbon $operationDate,
        ?string $voucherPath
    ): BankOperation {
        if (empty($data['target_bank_account_id'])) {
            throw new BankLedgerException('La cuenta de destino es obligatoria.', 422);
        }

        if ($data['bank_account_id'] === $data['target_bank_account_id']) {
            throw new BankLedgerException('La cuenta de origen y destino no pueden ser iguales.', 422);
        }

        $sourceId = (string) $data['bank_account_id'];
        $targetId = (string) $data['target_bank_account_id'];

        $firstId = strcmp($sourceId, $targetId) < 0 ? $sourceId : $targetId;
        $secondId = $firstId === $sourceId ? $targetId : $sourceId;

        /** @var BankAccount $acc1 */
        $acc1 = BankAccount::with('currency')->where('id', $firstId)->lockForUpdate()->firstOrFail();
        /** @var BankAccount $acc2 */
        $acc2 = BankAccount::with('currency')->where('id', $secondId)->lockForUpdate()->firstOrFail();

        $sourceAccount = $acc1->id === $sourceId ? $acc1 : $acc2;
        $targetAccount = $acc1->id === $targetId ? $acc1 : $acc2;

        if ($sourceAccount->currency === null || $targetAccount->currency === null) {
            throw new BankLedgerException('Las cuentas bancarias deben tener una moneda asignada.', 422);
        }

        if ($sourceAccount->currency_id === $targetAccount->currency_id) {
            $rate = 1.0;
            $targetAmount = $amount;
        } else {
            if (! empty($data['exchange_rate']) && (float) $data['exchange_rate'] > 0) {
                $rate = (float) $data['exchange_rate'];
                $targetAmount = (float) bcmul((string) $amount, (string) $rate, 4);
            } else {
                try {
                    $targetAmount = $this->exchangeRateService->convert(
                        amount: $amount,
                        from: $sourceAccount->currency,
                        to: $targetAccount->currency,
                        date: $operationDate,
                    );
                    $rate = $amount > 0 ? (float) ($targetAmount / $amount) : 1.0;
                } catch (FinanceRuleException $e) {
                    throw new BankLedgerException($e->getMessage(), 422);
                }
            }
        }

        /** @var BankOperation $operation */
        $operation = BankOperation::create([
            'number' => $number,
            'type' => BankOperationType::Transfer,
            'status' => BankOperationStatus::Completed,
            'bank_account_id' => $sourceAccount->id,
            'target_bank_account_id' => $targetAccount->id,
            'amount' => number_format($amount, 2, '.', ''),
            'target_amount' => number_format($targetAmount, 2, '.', ''),
            'exchange_rate' => number_format($rate, 4, '.', ''),
            'operation_number' => ! empty($data['operation_number']) ? trim((string) $data['operation_number']) : null,
            'operation_date' => $operationDate->toDateString(),
            'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : null,
            'voucher_path' => $voucherPath,
            'created_by' => $user->id,
        ]);

        $this->bankLedgerService->register(new BankMovementData(
            bankAccountId: $sourceAccount->id,
            direction: MoneyDirection::Out,
            conceptCode: SystemConcept::BankTransferOut->value,
            amount: $amount,
            movementDate: $operationDate,
            source: $operation,
            sourceNumber: $operation->number,
            operationNumber: $operation->operation_number,
            description: "Transferencia a {$targetAccount->alias} ({$operation->number})",
            voucherPath: $voucherPath,
            branchId: $sourceAccount->branch_id,
            createdBy: $user->id,
        ));

        $this->bankLedgerService->register(new BankMovementData(
            bankAccountId: $targetAccount->id,
            direction: MoneyDirection::In,
            conceptCode: SystemConcept::BankTransferIn->value,
            amount: $targetAmount,
            movementDate: $operationDate,
            source: $operation,
            sourceNumber: $operation->number,
            operationNumber: $operation->operation_number,
            description: "Transferencia desde {$sourceAccount->alias} ({$operation->number})",
            voucherPath: $voucherPath,
            branchId: $targetAccount->branch_id,
            createdBy: $user->id,
        ));

        return $operation;
    }

    public function cancel(BankOperation $operation, string $reason, User $user): BankOperation
    {
        return DB::transaction(function () use ($operation, $reason, $user) {
            /** @var BankOperation $locked */
            $locked = BankOperation::with(['bankMovements.period', 'cashMovements.session'])
                ->where('id', $operation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== BankOperationStatus::Completed) {
                throw new BankLedgerException('Solo se pueden anular operaciones bancarias completadas.', 422);
            }

            foreach ($locked->bankMovements as $bankMovement) {
                $this->bankLedgerService->reverse(
                    movement: $bankMovement,
                    source: $locked,
                    sourceNumber: $locked->number,
                    reason: $reason,
                    createdBy: $user->id,
                );
            }

            foreach ($locked->cashMovements as $cashMovement) {
                $this->cashLedgerService->reverse(
                    movement: $cashMovement,
                    source: $locked,
                    sourceNumber: $locked->number,
                    reason: $reason,
                );
            }

            $locked->update([
                'status' => BankOperationStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $locked;
        });
    }
}
