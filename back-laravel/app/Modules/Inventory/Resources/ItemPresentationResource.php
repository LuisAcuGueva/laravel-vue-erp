<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\ItemPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ItemPresentation
 */
class ItemPresentationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ItemPresentation $presentation */
        $presentation = $this->resource;

        return [
            'id' => $presentation->id,
            'catalog_item_id' => $presentation->catalog_item_id,
            'unit_id' => $presentation->unit_id,
            'name' => $presentation->name,
            'factor' => (float) $presentation->factor,
            'barcode' => $presentation->barcode,
            'is_base' => $presentation->is_base,
            'is_default' => $presentation->is_default,
            'is_active' => $presentation->is_active,
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $presentation->unit->id,
                'name' => $presentation->unit->name,
                'symbol' => $presentation->unit->symbol,
                'allow_decimals' => $presentation->unit->allow_decimals,
                'sunat_code' => $presentation->unit->sunat_code,
            ]),
            'prices' => ItemPriceResource::collection($this->whenLoaded('prices')),
            'created_at' => $presentation->created_at?->toIso8601String(),
            'updated_at' => $presentation->updated_at?->toIso8601String(),
        ];
    }
}
