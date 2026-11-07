<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\WarehouseNoteItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WarehouseNoteItem
 */
class WarehouseNoteItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_note_id' => $this->warehouse_note_id,
            'line' => $this->line,
            'catalog_item' => $this->whenLoaded('catalogItem', function () {
                return [
                    'id' => $this->catalogItem->id,
                    'code' => $this->catalogItem->code,
                    'name' => $this->catalogItem->name,
                    'base_unit' => $this->whenLoaded('catalogItem.baseUnit', function () {
                        return [
                            'id' => $this->catalogItem->baseUnit->id,
                            'sunat_code' => $this->catalogItem->baseUnit->sunat_code,
                            'symbol' => $this->catalogItem->baseUnit->symbol,
                            'name' => $this->catalogItem->baseUnit->name,
                            'allow_decimals' => $this->catalogItem->baseUnit->allow_decimals,
                        ];
                    }),
                ];
            }),
            'presentation' => $this->whenLoaded('presentation', function () {
                return [
                    'id' => $this->presentation->id,
                    'name' => $this->presentation->name,
                    'factor' => (float) $this->presentation->factor,
                    'unit' => $this->whenLoaded('presentation.unit', function () {
                        return [
                            'id' => $this->presentation->unit->id,
                            'sunat_code' => $this->presentation->unit->sunat_code,
                            'symbol' => $this->presentation->unit->symbol,
                            'name' => $this->presentation->unit->name,
                        ];
                    }),
                ];
            }),
            'quantity' => (float) $this->quantity,
            'base_quantity' => (float) $this->base_quantity,
            'unit_cost' => $this->unit_cost !== null ? (float) $this->unit_cost : null,
            'base_unit_cost' => $this->base_unit_cost !== null ? (float) $this->base_unit_cost : null,
            'total_cost' => (float) $this->total_cost,
            'notes' => $this->notes,
        ];
    }
}
