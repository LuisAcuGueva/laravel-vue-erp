<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Resources;

use App\Modules\Maintenance\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Area
 */
class AreaOptionResource extends JsonResource
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
        ];
    }
}
