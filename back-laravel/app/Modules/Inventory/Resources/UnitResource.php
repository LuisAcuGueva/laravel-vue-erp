<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Unit
 */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Unit $unit */
        $unit = $this->resource;

        return [
            'id' => $unit->id,
            'unit_category_id' => $unit->unit_category_id,
            'name' => $unit->name,
            'symbol' => $unit->symbol,
            'is_base' => $unit->is_base,
            'conversion_factor' => $unit->conversion_factor,
            'allow_decimals' => $unit->allow_decimals,
            'sunat_code' => $unit->sunat_code,
            'is_active' => $unit->is_active,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $unit->category->id,
                'name' => $unit->category->name,
                'is_packaging' => $unit->category->is_packaging,
            ]),
            'created_at' => $unit->created_at?->toIso8601String(),
            'updated_at' => $unit->updated_at?->toIso8601String(),
        ];
    }
}
