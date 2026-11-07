<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\CatalogItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CatalogItem
 */
class CatalogItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CatalogItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'type' => $item->type->value,
            'type_label' => $item->type->label(),
            'category_id' => $item->category_id,
            'brand_id' => $item->brand_id,
            'base_unit_id' => $item->base_unit_id,
            'description' => $item->description,
            'tax_type' => $item->tax_type->value,
            'tax_type_label' => $item->tax_type->label(),
            'track_stock' => $item->track_stock,
            'is_sellable' => $item->is_sellable,
            'is_purchasable' => $item->is_purchasable,
            'is_active' => $item->is_active,
            'last_purchase_cost' => $item->last_purchase_cost !== null ? (float) $item->last_purchase_cost : null,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $item->category->id,
                'name' => $item->category->name,
                'code' => $item->category->code,
            ]),
            'brand' => $this->whenLoaded('brand', fn () => $item->brand !== null ? [
                'id' => $item->brand->id,
                'name' => $item->brand->name,
            ] : null),
            'base_unit' => $this->whenLoaded('baseUnit', fn () => [
                'id' => $item->baseUnit->id,
                'name' => $item->baseUnit->name,
                'symbol' => $item->baseUnit->symbol,
                'allow_decimals' => $item->baseUnit->allow_decimals,
            ]),
            'default_presentation' => ItemPresentationResource::make($this->whenLoaded('defaultPresentation')),
            'presentations' => ItemPresentationResource::collection($this->whenLoaded('presentations')),
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }
}
