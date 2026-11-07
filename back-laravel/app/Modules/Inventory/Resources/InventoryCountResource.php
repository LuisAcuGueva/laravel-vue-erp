<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\InventoryCount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryCount
 */
class InventoryCountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $itemsLoaded = $this->relationLoaded('items');
        $totalItems = $itemsLoaded ? $this->items->count() : ($this->items_count ?? 0);
        $countedItems = $itemsLoaded
            ? $this->items->whereNotNull('counted_quantity')->count()
            : ($this->counted_items_count ?? 0);

        $summary = null;
        if ($itemsLoaded) {
            $positiveValue = 0.0;
            $negativeValue = 0.0;

            foreach ($this->items as $item) {
                $val = (float) ($item->difference_value ?? 0.0);
                if ($val > 0) {
                    $positiveValue += $val;
                } elseif ($val < 0) {
                    $negativeValue += abs($val);
                }
            }

            $summary = [
                'total' => $totalItems,
                'counted' => $countedItems,
                'positive_value' => round($positiveValue, 4),
                'negative_value' => round($negativeValue, 4),
                'net_value' => round($positiveValue - $negativeValue, 4),
            ];
        }

        return [
            'id' => $this->id,
            'number' => $this->number,
            'branch' => $this->whenLoaded('branch', function () {
                return [
                    'id' => $this->branch->id,
                    'name' => $this->branch->name,
                    'code' => $this->branch->code,
                ];
            }),
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                    'code' => $this->warehouse->code,
                ];
            }),
            'category' => $this->whenLoaded('category', function () {
                if ($this->category === null) {
                    return null;
                }

                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'code' => $this->category->code,
                ];
            }),
            'count_date' => $this->count_date->format('Y-m-d'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'notes' => $this->notes,
            'creator' => $this->whenLoaded('creator', function () {
                return $this->creator !== null ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ] : null;
            }),
            'closer' => $this->whenLoaded('closer', function () {
                return $this->closer ? [
                    'id' => $this->closer->id,
                    'name' => $this->closer->name,
                ] : null;
            }),
            'closed_at' => $this->closed_at?->toISOString(),
            'canceller' => $this->whenLoaded('canceller', function () {
                return $this->canceller ? [
                    'id' => $this->canceller->id,
                    'name' => $this->canceller->name,
                ] : null;
            }),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'total_items' => $totalItems,
            'counted_items' => $countedItems,
            'summary' => $summary,
            'items' => $this->whenLoaded('items', fn () => InventoryCountItemResource::collection($this->items)),
            'adjustment_notes' => $this->whenLoaded('warehouseNotes', fn () => WarehouseNoteResource::collection($this->warehouseNotes)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
