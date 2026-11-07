<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Services\ExchangeRateService;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Purchases\Exceptions\PurchasesRuleException;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\ProviderPrice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProviderPriceService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    /**
     * @return Collection<int, ProviderPrice>
     */
    public function listForProvider(Provider $provider): Collection
    {
        return $provider->prices()
            ->with(['presentation.item:id,code,name', 'currency:id,code,symbol'])
            ->orderByDesc('valid_from')
            ->get();
    }

    public function findById(string $id): ?ProviderPrice
    {
        return ProviderPrice::query()
            ->with(['presentation.item:id,code,name', 'currency:id,code,symbol'])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws PurchasesRuleException
     */
    public function create(Provider $provider, array $attributes): ProviderPrice
    {
        $this->assertNoOverlap(
            $provider->id,
            (string) $attributes['item_presentation_id'],
            (string) $attributes['currency_id'],
            (string) $attributes['valid_from'],
            isset($attributes['valid_until']) ? (string) $attributes['valid_until'] : null,
        );

        $price = $provider->prices()->create($attributes);

        return $this->findById($price->id) ?? $price;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws PurchasesRuleException
     */
    public function update(ProviderPrice $price, array $attributes): ProviderPrice
    {
        $this->assertNoOverlap(
            $price->provider_id,
            (string) $attributes['item_presentation_id'],
            (string) $attributes['currency_id'],
            (string) $attributes['valid_from'],
            isset($attributes['valid_until']) ? (string) $attributes['valid_until'] : null,
            $price->id,
        );

        $price->update($attributes);

        return $this->findById($price->id) ?? $price;
    }

    public function delete(ProviderPrice $price): bool
    {
        return (bool) $price->delete();
    }

    /**
     * Precios vigentes hoy de todos los proveedores activos para una presentación, de menor a mayor.
     * La conversión a moneda base solo sirve para ordenar; un precio sin tipo de cambio va al final.
     *
     * @return array{prices: Collection<int, ProviderPrice>, last_purchase_cost: string|null}
     */
    public function compare(ItemPresentation $presentation): array
    {
        $today = Carbon::today();
        $base = Currency::base();

        $prices = ProviderPrice::query()
            ->where('item_presentation_id', $presentation->id)
            ->validOn($today)
            ->whereHas('provider', fn ($q) => $q->where('is_active', true))
            ->with(['provider:id,document_type,document_number,business_name', 'currency'])
            ->get();

        foreach ($prices as $price) {
            $price->setAttribute('base_price', $this->toBase($price, $base, $today));
        }

        $sorted = $prices->sortBy([
            fn (ProviderPrice $a, ProviderPrice $b): int => ($a->getAttribute('base_price') ?? PHP_FLOAT_MAX) <=> ($b->getAttribute('base_price') ?? PHP_FLOAT_MAX),
        ])->values();

        $item = $presentation->item;

        return [
            'prices' => new Collection($sorted->all()),
            'last_purchase_cost' => $item?->last_purchase_cost,
        ];
    }

    /**
     * Registra el último precio pagado (lo invoca la recepción de compras, spec 023).
     * Actualiza el precio vigente del proveedor para la presentación y moneda, o crea uno nuevo desde `$at`.
     */
    public function recordPurchase(Provider $provider, ItemPresentation $presentation, Currency $currency, string $price, Carbon $at): ProviderPrice
    {
        return DB::transaction(function () use ($provider, $presentation, $currency, $price, $at): ProviderPrice {
            $current = ProviderPrice::query()
                ->where('provider_id', $provider->id)
                ->where('item_presentation_id', $presentation->id)
                ->where('currency_id', $currency->id)
                ->validOn($at)
                ->orderByDesc('valid_from')
                ->first();

            if ($current !== null) {
                $current->update(['last_purchase_price' => $price, 'last_purchase_at' => $at]);

                return $current;
            }

            return ProviderPrice::create([
                'provider_id' => $provider->id,
                'item_presentation_id' => $presentation->id,
                'currency_id' => $currency->id,
                'price' => $price,
                'valid_from' => $at->toDateString(),
                'valid_until' => null,
                'last_purchase_price' => $price,
                'last_purchase_at' => $at,
            ]);
        });
    }

    private function toBase(ProviderPrice $price, ?Currency $base, Carbon $date): ?float
    {
        if ($base === null) {
            return null;
        }

        try {
            return $this->exchangeRateService->convert((float) $price->price, $price->currency, $base, $date, 'buy');
        } catch (FinanceRuleException) {
            return null;
        }
    }

    /**
     * Dos vigencias se superponen si cada una empieza antes de que termine la otra (`valid_until` nulo = sin fin).
     *
     * @throws PurchasesRuleException
     */
    private function assertNoOverlap(string $providerId, string $presentationId, string $currencyId, string $from, ?string $until, ?string $ignoreId = null): void
    {
        $overlaps = ProviderPrice::query()
            ->where('provider_id', $providerId)
            ->where('item_presentation_id', $presentationId)
            ->where('currency_id', $currencyId)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->when($until !== null, fn ($q) => $q->whereDate('valid_from', '<=', $until))
            ->where(function ($q) use ($from) {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $from);
            })
            ->exists();

        if ($overlaps) {
            throw new PurchasesRuleException('La vigencia se superpone con otro precio del mismo proveedor, presentación y moneda', 'valid_from');
        }
    }
}
