<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\Bank;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BankService
{
    /**
     * @return Builder<Bank>
     */
    public function query(): Builder
    {
        return Bank::query();
    }

    /**
     * @return LengthAwarePaginator<int, Bank>
     */
    public function paginate(?string $search = null, ?bool $active = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query();

        if ($search !== null && trim($search) !== '') {
            $query->search($search);
        }

        if ($active !== null) {
            $query->where('is_active', $active);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * @return Collection<int, Bank>
     */
    public function options(): Collection
    {
        return Bank::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function find(string $id): Bank
    {
        return Bank::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Bank
    {
        /** @var Bank $bank */
        $bank = Bank::create($attributes);

        return $bank;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Bank $bank, array $attributes): Bank
    {
        $bank->update($attributes);

        return $bank->fresh() ?? $bank;
    }

    public function delete(Bank $bank): bool
    {
        if ($bank->accounts()->exists()) {
            throw new FinanceRuleException('No se puede eliminar un banco con cuentas asociadas', 'bank');
        }

        return (bool) $bank->delete();
    }
}
