<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\ItemPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ItemPresentation
 */
class CatalogLookupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ItemPresentation $presentation */
        $presentation = $this->resource;
        $item = $presentation->item;
        $unit = $presentation->unit;

        $retailPrice = null;
        $wholesalePrice = null;
        $wholesaleMinQty = null;

        foreach ($presentation->prices as $price) {
            if ($price->price_type->value === 'retail') {
                $retailPrice = (float) $price->price;
            } elseif ($price->price_type->value === 'wholesale') {
                $wholesalePrice = (float) $price->price;
                $wholesaleMinQty = (float) $price->min_quantity;
            }
        }

        return [
            'presentation_id' => $presentation->id,
            'catalog_item_id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'presentation_name' => $presentation->name,
            'factor' => (float) $presentation->factor,
            'barcode' => $presentation->barcode,
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'symbol' => $unit->symbol,
                'sunat_code' => $unit->sunat_code,
            ],
            'type' => $item->type->value,
            'tax_type' => $item->tax_type->value,
            'track_stock' => $item->track_stock,
            'prices' => [
                'retail' => $retailPrice,
                'wholesale' => $wholesalePrice,
                'wholesale_min_quantity' => $wholesaleMinQty,
            ],
            'stock' => $presentation->getAttribute('stock') ?? null,
        ];
    }
}
