<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\PurchaseReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseReceiptItem
 */
class PurchaseReceiptItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_receipt_id' => $this->purchase_receipt_id,
            'line' => $this->line,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'catalog_item_id' => $this->catalog_item_id,
            'item_presentation_id' => $this->item_presentation_id,
            'is_service' => $this->is_service,
            'quantity' => $this->quantity,
            'base_quantity' => $this->base_quantity,
            'unit_cost' => $this->unit_cost,
            'base_unit_cost' => $this->base_unit_cost,
            'total_cost' => $this->total_cost,
            'notes' => $this->notes,
            'catalog_item' => $this->whenLoaded('catalogItem', fn () => $this->catalogItem ? [
                'id' => $this->catalogItem->id,
                'code' => $this->catalogItem->code,
                'name' => $this->catalogItem->name,
                'track_stock' => $this->catalogItem->track_stock,
            ] : null),
            'presentation' => $this->whenLoaded('presentation', fn () => $this->presentation ? [
                'id' => $this->presentation->id,
                'name' => $this->presentation->name,
                'factor' => $this->presentation->factor,
            ] : null),
            'order_item' => $this->whenLoaded('orderItem', fn () => $this->orderItem ? [
                'id' => $this->orderItem->id,
                'line' => $this->orderItem->line,
                'description' => $this->orderItem->description,
                'quantity' => $this->orderItem->quantity,
                'received_quantity' => $this->orderItem->received_quantity,
                'pending_quantity' => $this->orderItem->pending_quantity,
            ] : null),
            'tax_type' => $this->whenLoaded('orderItem', fn () => $this->orderItem?->tax_type?->value),
        ];
    }
}
