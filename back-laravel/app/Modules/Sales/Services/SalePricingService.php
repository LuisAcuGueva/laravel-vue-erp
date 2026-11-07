<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Finance\Models\Currency;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\ItemPrice;
use App\Support\Pricing\Decimal;

class SalePricingService
{
    /**
     * Obtiene el precio de lista para una presentación, tipo de precio y cantidad,
     * convirtiéndolo a la moneda del documento si difiere de la moneda base.
     */
    public function listPrice(
        ItemPresentation $presentation,
        PriceType|string $type,
        float $quantity,
        Currency $currency,
        string $rate = '1'
    ): ?string {
        $priceType = is_string($type) ? PriceType::from($type) : $type;

        $prices = $presentation->relationLoaded('prices')
            ? $presentation->prices
            : $presentation->prices()->get();

        $matchedPrice = null;

        if ($priceType === PriceType::Wholesale) {
            $wholesalePrices = $prices->filter(
                fn (ItemPrice $price) => $price->price_type === PriceType::Wholesale && (float) $price->min_quantity <= $quantity
            )->sortByDesc(fn (ItemPrice $price) => (float) $price->min_quantity);

            $matchedPrice = $wholesalePrices->first();
        }

        // Si es minorista o si mayorista no cumplió la cantidad mínima, cae a retail
        if ($matchedPrice === null) {
            $matchedPrice = $prices->first(
                fn (ItemPrice $price) => $price->price_type === PriceType::Retail
            );
        }

        if ($matchedPrice === null) {
            return null;
        }

        $basePrice = (string) $matchedPrice->price;

        // Si la moneda es la base o la tasa es neutra, retorna el precio base
        if ($currency->is_base || $rate === '1' || (float) $rate === 1.0 || (float) $rate <= 0.0) {
            return number_format((float) $basePrice, 4, '.', '');
        }

        // Conversión a la moneda del documento: precio base / tipo de cambio
        $converted = Decimal::div($basePrice, $rate, 6);

        return number_format((float) $converted, 4, '.', '');
    }
}
