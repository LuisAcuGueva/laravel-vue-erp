<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\UnitCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UnitCategory
 */
class UnitCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UnitCategory $unitCategory */
        $unitCategory = $this->resource;

        return [
            'id' => $unitCategory->id,
            'name' => $unitCategory->name,
            'description' => $unitCategory->description,
            'is_packaging' => $unitCategory->is_packaging,
            'is_active' => $unitCategory->is_active,
            'units' => UnitResource::collection($this->whenLoaded('units')),
            'base_unit' => $this->whenLoaded('baseUnit', fn () => $unitCategory->baseUnit !== null ? new UnitResource($unitCategory->baseUnit) : null),
            'units_count' => $this->whenCounted('units'),
            'created_at' => $unitCategory->created_at?->toIso8601String(),
            'updated_at' => $unitCategory->updated_at?->toIso8601String(),
        ];
    }
}
