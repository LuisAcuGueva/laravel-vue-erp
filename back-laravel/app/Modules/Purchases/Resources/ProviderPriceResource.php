<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\ProviderPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderPrice
 */
class ProviderPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProviderPrice $price */
        $price = $this->resource;

        return [
            'id' => $price->id,
            'provider_id' => $price->provider_id,
            'item_presentation_id' => $price->item_presentation_id,
            'currency_id' => $price->currency_id,
            'price' => $price->price,
            'valid_from' => $price->valid_from->format('Y-m-d'),
            'valid_until' => $price->valid_until?->format('Y-m-d'),
            'notes' => $price->notes,
            'last_purchase_price' => $price->last_purchase_price,
            'last_purchase_at' => $price->last_purchase_at?->toIso8601String(),
            'base_price' => $price->getAttribute('base_price'),
            'presentation' => $this->whenLoaded('presentation', fn () => [
                'id' => $price->presentation->id,
                'name' => $price->presentation->name,
                'item' => $price->presentation->relationLoaded('item') && $price->presentation->item ? [
                    'id' => $price->presentation->item->id,
                    'code' => $price->presentation->item->code,
                    'name' => $price->presentation->item->name,
                ] : null,
            ]),
            'currency' => $this->whenLoaded('currency', fn () => [
                'id' => $price->currency->id,
                'code' => $price->currency->code,
                'symbol' => $price->currency->symbol,
            ]),
            'provider' => $this->whenLoaded('provider', fn () => [
                'id' => $price->provider->id,
                'document_number' => $price->provider->document_number,
                'business_name' => $price->provider->business_name,
            ]),
        ];
    }
}
