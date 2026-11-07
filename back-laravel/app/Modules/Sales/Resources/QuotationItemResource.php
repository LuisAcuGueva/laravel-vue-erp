<?php

declare(strict_types=1);

namespace App\Modules\Sales\Resources;

use App\Modules\Sales\Models\QuotationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationItem
 */
class QuotationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quotation_id' => $this->quotation_id,
            'line' => $this->line,
            'catalog_item_id' => $this->catalog_item_id,
            'item_presentation_id' => $this->item_presentation_id,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'list_price' => $this->list_price !== null ? (float) $this->list_price : null,
            'unit_price' => (float) $this->unit_price,
            'discount_percent' => (float) $this->discount_percent,
            'effective_discount_percent' => (float) $this->effective_discount_percent,
            'tax_type' => $this->tax_type->value,
            'tax_type_label' => $this->tax_type->label(),
            'line_subtotal' => (float) $this->line_subtotal,
            'line_tax' => (float) $this->line_tax,
            'line_total' => (float) $this->line_total,
            'catalog_item' => $this->whenLoaded('catalogItem', fn () => $this->catalogItem ? [
                'id' => $this->catalogItem->id,
                'code' => $this->catalogItem->code,
                'name' => $this->catalogItem->name,
                'is_sellable' => $this->catalogItem->is_sellable,
            ] : null),
            'presentation' => $this->whenLoaded('presentation', fn () => $this->presentation ? [
                'id' => $this->presentation->id,
                'name' => $this->presentation->name,
                'factor' => (float) $this->presentation->factor,
                'barcode' => $this->presentation->barcode,
            ] : null),
        ];
    }
}
