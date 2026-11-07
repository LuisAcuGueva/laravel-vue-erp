<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\WarehouseReason;
use App\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KardexMovement
 */
class KardexMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var KardexMovement $m */
        $m = $this->resource;
        /** @var WarehouseReason|null $reason */
        $reason = $m->reason;
        /** @var User|null $user */
        $user = $m->user;
        /** @var ItemPresentation|null $pres */
        $pres = $m->presentation;

        return [
            'id' => $m->id,
            'moved_at' => $m->moved_at->toIso8601String(),
            'movement_type' => $m->movement_type->value,
            'direction' => $m->direction->value,
            'reason' => $reason ? [
                'id' => $reason->id,
                'code' => $reason->code,
                'name' => $reason->name,
            ] : null,
            'source_type' => $m->source_type,
            'source_id' => $m->source_id,
            'source_number' => $m->source_number,
            'quantity' => $m->quantity,
            'presentation_quantity' => $m->presentation_quantity,
            'unit_cost' => $m->unit_cost,
            'total_cost' => $m->total_cost,
            'balance_quantity' => $m->balance_quantity,
            'balance_unit_cost' => $m->balance_unit_cost,
            'balance_total_cost' => $m->balance_total_cost,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
            ] : null,
            'presentation' => $pres ? [
                'id' => $pres->id,
                'name' => $pres->name,
                'factor' => $pres->factor,
            ] : null,
            'notes' => $m->notes,
        ];
    }
}
