<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Configuration\Services\CompanySettingService;
use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\Currency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CurrencyService
{
    public function __construct(
        protected CompanySettingService $companySettingService,
    ) {}

    /**
     * @return Builder<Currency>
     */
    public function query(): Builder
    {
        return Currency::query();
    }

    /**
     * @return LengthAwarePaginator<int, Currency>
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

        return $query->orderByDesc('is_base')->orderBy('code')->paginate($perPage);
    }

    /**
     * @return Collection<int, Currency>
     */
    public function options(): Collection
    {
        return Currency::query()
            ->active()
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->get();
    }

    public function find(string $id): Currency
    {
        return Currency::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Currency
    {
        $isBase = (bool) ($attributes['is_base'] ?? false);

        if ($isBase) {
            return DB::transaction(function () use ($attributes) {
                Currency::where('is_base', true)->update(['is_base' => false]);
                /** @var Currency $currency */
                $currency = Currency::create(array_merge($attributes, ['is_base' => true]));
                $this->companySettingService->setDefaultCurrency($currency->code);
                Currency::clearBaseCache();

                return $currency;
            });
        }

        /** @var Currency $currency */
        $currency = Currency::create($attributes);

        return $currency;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Currency $currency, array $attributes): Currency
    {
        $wantsBase = array_key_exists('is_base', $attributes) ? (bool) $attributes['is_base'] : null;
        $wantsActive = array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : null;

        if ($currency->is_base && $wantsBase === false) {
            throw new FinanceRuleException('La empresa debe tener exactamente una moneda base', 'is_base');
        }

        if ($currency->is_base && $wantsActive === false) {
            throw new FinanceRuleException('La moneda base no se puede desactivar', 'is_active');
        }

        if (! $currency->is_base && $wantsBase === true) {
            BaseCurrencyGuard::assertCanChangeBase();

            return DB::transaction(function () use ($currency, $attributes) {
                Currency::where('is_base', true)
                    ->where('id', '!=', $currency->id)
                    ->update(['is_base' => false]);

                $currency->update(array_merge($attributes, ['is_base' => true]));
                $fresh = $currency->fresh() ?? $currency;
                $this->companySettingService->setDefaultCurrency($fresh->code);
                Currency::clearBaseCache();

                return $fresh;
            });
        }

        if ($currency->is_base && isset($attributes['code']) && $attributes['code'] !== $currency->code) {
            return DB::transaction(function () use ($currency, $attributes) {
                $currency->update($attributes);
                $fresh = $currency->fresh() ?? $currency;
                $this->companySettingService->setDefaultCurrency($fresh->code);
                Currency::clearBaseCache();

                return $fresh;
            });
        }

        $currency->update($attributes);
        Currency::clearBaseCache();

        return $currency->fresh() ?? $currency;
    }

    public function delete(Currency $currency): bool
    {
        if ($currency->is_base) {
            throw new FinanceRuleException('La moneda base no se puede eliminar', 'is_base');
        }

        if ($currency->exchangeRates()->exists() || $currency->bankAccounts()->exists()) {
            throw new FinanceRuleException('No se puede eliminar una moneda con registros asociados', 'currency');
        }

        Currency::clearBaseCache();

        return (bool) $currency->delete();
    }
}
