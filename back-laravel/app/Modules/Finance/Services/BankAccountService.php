<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\Currency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BankAccountService
{
    /**
     * @return Builder<BankAccount>
     */
    public function query(): Builder
    {
        return BankAccount::with(['bank', 'currency', 'branch', 'openPeriod']);
    }

    /**
     * @return LengthAwarePaginator<int, BankAccount>
     */
    public function paginate(
        ?string $search = null,
        ?string $bankId = null,
        ?string $currencyId = null,
        ?string $branchId = null,
        ?bool $active = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $this->query();

        if ($search !== null && trim($search) !== '') {
            $query->search($search);
        }

        if ($bankId !== null) {
            $query->where('bank_id', $bankId);
        }

        if ($currencyId !== null) {
            $query->where('currency_id', $currencyId);
        }

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($active !== null) {
            $query->where('is_active', $active);
        }

        return $query->orderBy('alias')->paginate($perPage);
    }

    /**
     * @return Collection<int, BankAccount>
     */
    public function options(?string $branchId = null): Collection
    {
        $query = BankAccount::with(['bank', 'currency'])
            ->active();

        if ($branchId !== null) {
            $query->where(function (Builder $q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        return $query->orderBy('alias')->get();
    }

    public function find(string $id): BankAccount
    {
        return BankAccount::with(['bank', 'currency', 'branch'])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): BankAccount
    {
        /** @var Bank $bank */
        $bank = Bank::query()->findOrFail($attributes['bank_id']);
        if (! $bank->is_active) {
            throw new FinanceRuleException('El banco seleccionado no está activo', 'bank_id');
        }

        /** @var Currency $currency */
        $currency = Currency::query()->findOrFail($attributes['currency_id']);
        if (! $currency->is_active) {
            throw new FinanceRuleException('La moneda seleccionada no está activa', 'currency_id');
        }

        /** @var BankAccount $account */
        $account = BankAccount::create($attributes);

        return $account->load(['bank', 'currency', 'branch']);
    }

    /**
     * Hook para validar si una cuenta bancaria tiene movimientos registrados en el ledger.
     */
    public function hasMovements(BankAccount $account): bool
    {
        return BankMovement::where('bank_account_id', $account->id)->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(BankAccount $account, array $attributes): BankAccount
    {
        if (isset($attributes['currency_id']) && $attributes['currency_id'] !== $account->currency_id) {
            if ($this->hasMovements($account)) {
                throw new FinanceRuleException('No se puede cambiar la moneda de una cuenta con movimientos', 'currency_id');
            }

            /** @var Currency $currency */
            $currency = Currency::query()->findOrFail($attributes['currency_id']);
            if (! $currency->is_active) {
                throw new FinanceRuleException('La moneda seleccionada no está activa', 'currency_id');
            }
        }

        if (isset($attributes['bank_id']) && $attributes['bank_id'] !== $account->bank_id) {
            /** @var Bank $bank */
            $bank = Bank::query()->findOrFail($attributes['bank_id']);
            if (! $bank->is_active) {
                throw new FinanceRuleException('El banco seleccionado no está activo', 'bank_id');
            }
        }

        $account->update($attributes);

        return $account->fresh(['bank', 'currency', 'branch']) ?? $account;
    }

    public function delete(BankAccount $account): bool
    {
        if ($this->hasMovements($account)) {
            throw new FinanceRuleException('No se puede eliminar una cuenta bancaria con movimientos', 'bank_account');
        }

        if ($account->paymentMethods()->exists()) {
            throw new FinanceRuleException('No se puede eliminar una cuenta bancaria asociada a métodos de pago', 'bank_account');
        }

        return (bool) $account->delete();
    }
}
