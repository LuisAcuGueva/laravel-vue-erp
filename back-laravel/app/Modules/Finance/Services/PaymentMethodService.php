<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PaymentMethodService
{
    /**
     * @return Builder<PaymentMethod>
     */
    public function query(): Builder
    {
        return PaymentMethod::with('bankAccount.bank');
    }

    /**
     * @return LengthAwarePaginator<int, PaymentMethod>
     */
    public function paginate(
        ?string $search = null,
        ?string $kind = null,
        ?string $settlement = null,
        ?bool $active = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $this->query();

        if ($search !== null && trim($search) !== '') {
            $query->search($search);
        }

        if ($kind !== null && trim($kind) !== '') {
            $query->where('kind', $kind);
        }

        if ($settlement !== null && trim($settlement) !== '') {
            $query->where('settlement', $settlement);
        }

        if ($active !== null) {
            $query->where('is_active', $active);
        }

        return $query->ordered()->paginate($perPage);
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    public function options(): Collection
    {
        return PaymentMethod::with('bankAccount.bank')
            ->active()
            ->ordered()
            ->get();
    }

    public function find(string $id): PaymentMethod
    {
        return PaymentMethod::with('bankAccount.bank')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PaymentMethod
    {
        $this->validateKindAndSettlement($attributes);

        /** @var PaymentMethod $method */
        $method = PaymentMethod::create($attributes);

        return $method->load('bankAccount.bank');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PaymentMethod $paymentMethod, array $attributes): PaymentMethod
    {
        if ($paymentMethod->is_system) {
            if (isset($attributes['code']) && $attributes['code'] !== $paymentMethod->code) {
                throw new FinanceRuleException('No se puede modificar el código de un método de pago de sistema', 'code');
            }

            if (isset($attributes['kind'])) {
                $newKind = $attributes['kind'] instanceof PaymentMethodKind ? $attributes['kind']->value : $attributes['kind'];
                if ($newKind !== $paymentMethod->kind->value) {
                    throw new FinanceRuleException('No se puede modificar el tipo de un método de pago de sistema', 'kind');
                }
            }
        }

        $mergedAttributes = array_merge($paymentMethod->toArray(), $attributes);
        $this->validateKindAndSettlement($mergedAttributes);

        $paymentMethod->update($attributes);

        return $paymentMethod->fresh('bankAccount.bank') ?? $paymentMethod;
    }

    public function delete(PaymentMethod $paymentMethod): bool
    {
        if ($paymentMethod->is_system) {
            throw new FinanceRuleException('No se puede eliminar un método de pago de sistema', 'payment_method');
        }

        return (bool) $paymentMethod->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validateKindAndSettlement(array $attributes): void
    {
        $kind = $attributes['kind'] ?? null;
        $kindValue = $kind instanceof PaymentMethodKind ? $kind->value : (string) $kind;

        $settlement = $attributes['settlement'] ?? null;
        $settlementValue = $settlement instanceof Settlement ? $settlement->value : (string) $settlement;

        if ($kindValue === PaymentMethodKind::Cash->value && $settlementValue !== Settlement::CashBox->value) {
            throw new FinanceRuleException('El método de pago en efectivo debe liquidar en caja', 'settlement');
        }

        if ($settlementValue === Settlement::BankAccount->value) {
            $bankAccountId = $attributes['bank_account_id'] ?? null;
            if ($bankAccountId === null || trim((string) $bankAccountId) === '') {
                throw new FinanceRuleException('Debe seleccionar una cuenta bancaria', 'bank_account_id');
            }

            /** @var BankAccount|null $account */
            $account = BankAccount::query()->find($bankAccountId);
            if ($account === null || ! $account->is_active) {
                throw new FinanceRuleException('Debe seleccionar una cuenta bancaria activa', 'bank_account_id');
            }
        }
    }
}
