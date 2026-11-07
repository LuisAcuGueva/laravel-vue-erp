<?php

declare(strict_types=1);

namespace App\Modules\Sales\Resources;

use App\Modules\Sales\Models\SaleItem;
use App\Support\Pricing\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaleItem
 */
class SaleItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewCosts = $user && $user->can('sales.view_costs');

        $margin = null;
        if ($canViewCosts) {
            $margin = Decimal::sub((string) $this->line_subtotal, (string) $this->cost_total, 2);
        }

        return [
            'id' => $this->id,
            'line' => $this->line,
            'catalog_item_id' => $this->catalog_item_id,
            'item_presentation_id' => $this->item_presentation_id,
            'description' => $this->description,
            'unit_sunat_code' => $this->unit_sunat_code,
            'quantity' => $this->quantity,
            'base_quantity' => $this->base_quantity,
            'list_price' => $this->list_price,
            'unit_price' => $this->unit_price,
            'discount_percent' => $this->discount_percent,
            'effective_discount_percent' => $this->effective_discount_percent,
            'tax_type' => is_object($this->tax_type) ? $this->tax_type->value : $this->tax_type,
            'line_subtotal' => $this->line_subtotal,
            'line_tax' => $this->line_tax,
            'line_total' => $this->line_total,
            'unit_cost' => $canViewCosts ? $this->unit_cost : null,
            'cost_total' => $canViewCosts ? $this->cost_total : null,
            'margin' => $margin,
            'item' => $this->catalogItem ? [
                'id' => $this->catalogItem->id,
                'name' => $this->catalogItem->name,
                'code' => $this->catalogItem->code,
                'track_stock' => $this->catalogItem->track_stock,
            ] : null,
            'presentation' => $this->itemPresentation ? [
                'id' => $this->itemPresentation->id,
                'name' => $this->itemPresentation->name,
                'factor' => $this->itemPresentation->factor,
            ] : null,
        ];
    }
}
