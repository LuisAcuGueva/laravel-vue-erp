<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Brand
 */
class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Brand $brand */
        $brand = $this->resource;

        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'description' => $brand->description,
            'is_active' => $brand->is_active,
            'created_at' => $brand->created_at?->toIso8601String(),
            'updated_at' => $brand->updated_at?->toIso8601String(),
        ];
    }
}
