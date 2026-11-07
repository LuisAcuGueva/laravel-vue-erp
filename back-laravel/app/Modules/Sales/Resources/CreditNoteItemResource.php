<?php

declare(strict_types=1);

namespace App\Modules\Sales\Resources;

use App\Modules\Sales\Models\CreditNoteItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditNoteItem
 */
class CreditNoteItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'credit_note_id' => $this->credit_note_id,
            'line' => $this->line,
            'sale_item_id' => $this->sale_item_id,
            'catalog_item_id' => $this->catalog_item_id,
            'item_presentation_id' => $this->item_presentation_id,
            'description' => $this->description,
            'quantity' => (string) $this->quantity,
            'base_quantity' => $this->base_quantity !== null ? (string) $this->base_quantity : null,
            'unit_price' => (string) $this->unit_price,
            'discount_percent' => (string) $this->discount_percent,
            'tax_type' => $this->tax_type,
            'line_subtotal' => (string) $this->line_subtotal,
            'line_tax' => (string) $this->line_tax,
            'line_total' => (string) $this->line_total,
            'return_to_stock' => (bool) $this->return_to_stock,
            'unit_cost' => (string) $this->unit_cost,
        ];
    }
}
