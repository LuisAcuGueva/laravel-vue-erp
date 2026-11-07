<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Warehouse
 */
class WarehouseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this->resource;

        return [
            'id' => $warehouse->id,
            'branch_id' => $warehouse->branch_id,
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'address' => $warehouse->address,
            'is_default' => $warehouse->is_default,
            'is_active' => $warehouse->is_active,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $warehouse->branch->id,
                'name' => $warehouse->branch->name,
                'code' => $warehouse->branch->code,
            ]),
            'created_at' => $warehouse->created_at?->toIso8601String(),
            'updated_at' => $warehouse->updated_at?->toIso8601String(),
        ];
    }
}
