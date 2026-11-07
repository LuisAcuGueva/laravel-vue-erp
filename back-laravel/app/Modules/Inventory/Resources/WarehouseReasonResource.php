<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\WarehouseReason;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WarehouseReason
 */
class WarehouseReasonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WarehouseReason $reason */
        $reason = $this->resource;

        return [
            'id' => $reason->id,
            'code' => $reason->code,
            'name' => $reason->name,
            'type' => $reason->type->value,
            'type_label' => $reason->type->label(),
            'requires_approval' => $reason->requires_approval,
            'is_system' => $reason->is_system,
            'is_active' => $reason->is_active,
            'created_at' => $reason->created_at?->toIso8601String(),
            'updated_at' => $reason->updated_at?->toIso8601String(),
        ];
    }
}
