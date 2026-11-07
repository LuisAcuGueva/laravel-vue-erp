<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Resources;

use App\Modules\Maintenance\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Area
 */
class AreaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Area $area */
        $area = $this->resource;

        return [
            'id' => $area->id,
            'name' => $area->name,
            'description' => $area->description,
            'is_active' => $area->is_active,
            'created_at' => $area->created_at?->toIso8601String(),
            'updated_at' => $area->updated_at?->toIso8601String(),
        ];
    }
}
