<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Stock
 */
class StockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Stock $stock */
        $stock = $this->resource;
        /** @var Warehouse $warehouse */
        $warehouse = $stock->warehouse;
        /** @var CatalogItem $catalogItem */
        $catalogItem = $stock->catalogItem;
        /** @var Unit $baseUnit */
        $baseUnit = $catalogItem->baseUnit;
        /** @var Category|null $category */
        $category = $catalogItem->category;

        return [
            'id' => $stock->id,
            'warehouse' => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ],
            'catalog_item' => [
                'id' => $catalogItem->id,
                'code' => $catalogItem->code,
                'name' => $catalogItem->name,
                'base_unit' => [
                    'id' => $baseUnit->id,
                    'name' => $baseUnit->name,
                    'symbol' => $baseUnit->symbol,
                ],
                'category' => $category ? [
                    'id' => $category->id,
                    'code' => $category->code,
                    'name' => $category->name,
                ] : null,
            ],
            'quantity' => $stock->quantity,
            'avg_cost' => $stock->avg_cost,
            'total_value' => $stock->total_value,
            'min_stock' => $stock->min_stock,
            'max_stock' => $stock->max_stock,
            'is_below_min' => $stock->is_below_min,
            'last_movement_at' => $stock->last_movement_at?->toIso8601String(),
        ];
    }
}
