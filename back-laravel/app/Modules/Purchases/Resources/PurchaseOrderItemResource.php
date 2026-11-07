<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderItem
 */
class PurchaseOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseOrderItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'line' => $item->line,
            'purchase_request_item_id' => $item->purchase_request_item_id,
            'catalog_item_id' => $item->catalog_item_id,
            'item_presentation_id' => $item->item_presentation_id,
            'description' => $item->description,
            'is_service' => $item->is_service,
            'quantity' => $item->quantity,
            'received_quantity' => $item->received_quantity,
            'pending_quantity' => $item->pending_quantity,
            'unit_price' => $item->unit_price,
            'discount_percent' => $item->discount_percent,
            'tax_type' => $item->tax_type->value,
            'line_subtotal' => $item->line_subtotal,
            'line_tax' => $item->line_tax,
            'line_total' => $item->line_total,
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
            'request_id' => $this->whenLoaded('requestItem', fn () => $item->requestItem?->relationLoaded('request') ? $item->requestItem->request->id : null),
            'request_number' => $this->whenLoaded('requestItem', fn () => $item->requestItem?->relationLoaded('request') ? $item->requestItem->request->number : null),
        ];
    }
}
