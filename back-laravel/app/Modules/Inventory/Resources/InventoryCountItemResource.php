<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\InventoryCountItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryCountItem
 */
class InventoryCountItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_count_id' => $this->inventory_count_id,
            'catalog_item_id' => $this->catalog_item_id,
            'catalog_item' => $this->whenLoaded('catalogItem', function () {
                $baseUnit = $this->catalogItem->baseUnit;

                return [
                    'id' => $this->catalogItem->id,
                    'code' => $this->catalogItem->code,
                    'name' => $this->catalogItem->name,
                    'base_unit' => $baseUnit ? [
                        'id' => $baseUnit->id,
                        'name' => $baseUnit->name,
                        'symbol' => $baseUnit->symbol,
                        'allow_decimals' => (bool) $baseUnit->allow_decimals,
                    ] : null,
                ];
            }),
            'system_quantity' => (float) $this->system_quantity,
            'counted_quantity' => $this->counted_quantity !== null ? (float) $this->counted_quantity : null,
            'closing_system_quantity' => $this->closing_system_quantity !== null ? (float) $this->closing_system_quantity : null,
            'difference' => $this->difference !== null ? (float) $this->difference : null,
            'unit_cost' => (float) $this->unit_cost,
            'difference_value' => $this->difference_value !== null ? (float) $this->difference_value : null,
            'counted_by' => $this->counted_by,
            'counter' => $this->whenLoaded('counter', function () {
                return $this->counter !== null ? [
                    'id' => $this->counter->id,
                    'name' => $this->counter->name,
                ] : null;
            }),
            'counted_at' => $this->counted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
