<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\ItemPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ItemPrice
 */
class ItemPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ItemPrice $price */
        $price = $this->resource;

        return [
            'id' => $price->id,
            'item_presentation_id' => $price->item_presentation_id,
            'price_type' => $price->price_type->value,
            'price_type_label' => $price->price_type->label(),
            'price' => (float) $price->price,
            'min_quantity' => (float) $price->min_quantity,
            'created_at' => $price->created_at?->toIso8601String(),
            'updated_at' => $price->updated_at?->toIso8601String(),
        ];
    }
}
