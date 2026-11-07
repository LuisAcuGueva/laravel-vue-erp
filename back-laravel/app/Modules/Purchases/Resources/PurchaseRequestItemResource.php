<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\PurchaseRequestItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseRequestItem
 */
class PurchaseRequestItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseRequestItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'purchase_request_id' => $item->purchase_request_id,
            'line' => $item->line,
            'catalog_item_id' => $item->catalog_item_id,
            'item_presentation_id' => $item->item_presentation_id,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'ordered_quantity' => $item->ordered_quantity,
            'pending_quantity' => $item->pending_quantity,
            'notes' => $item->notes,
            'catalog_item' => $this->whenLoaded('catalogItem', fn () => $item->catalogItem ? [
                'id' => $item->catalogItem->id,
                'code' => $item->catalogItem->code,
                'name' => $item->catalogItem->name,
            ] : null),
            'presentation' => $this->whenLoaded('presentation', fn () => $item->presentation ? [
                'id' => $item->presentation->id,
                'name' => $item->presentation->name,
                'unit' => $item->presentation->relationLoaded('unit') && $item->presentation->unit ? [
                    'name' => $item->presentation->unit->name,
                    'symbol' => $item->presentation->unit->symbol,
                ] : null,
            ] : null),
            'request' => $this->whenLoaded('request', fn () => [
                'id' => $item->request->id,
                'number' => $item->request->number,
                'required_date' => $item->request->required_date->format('Y-m-d'),
                'priority' => $item->request->priority->value,
                'area' => $item->request->relationLoaded('area') ? ['id' => $item->request->area->id, 'name' => $item->request->area->name] : null,
            ]),
        ];
    }
}
