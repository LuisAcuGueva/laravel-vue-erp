<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\WarehouseNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WarehouseNote
 */
class WarehouseNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'branch' => $this->whenLoaded('branch', function () {
                return [
                    'id' => $this->branch->id,
                    'name' => $this->branch->name,
                    'code' => $this->branch->code,
                ];
            }),
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                    'code' => $this->warehouse->code,
                ];
            }),
            'target_warehouse' => $this->whenLoaded('targetWarehouse', function () {
                if ($this->targetWarehouse === null) {
                    return null;
                }

                return [
                    'id' => $this->targetWarehouse->id,
                    'name' => $this->targetWarehouse->name,
                    'code' => $this->targetWarehouse->code,
                    'branch_id' => $this->targetWarehouse->branch_id,
                ];
            }),
            'reason' => $this->whenLoaded('reason', function () {
                return [
                    'id' => $this->reason->id,
                    'name' => $this->reason->name,
                    'code' => $this->reason->code,
                    'type' => $this->reason->type->value,
                ];
            }),
            'inventory_count_id' => $this->inventory_count_id,
            'document_date' => $this->document_date->toDateString(),
            'reference_document' => $this->reference_document,
            'observation' => $this->observation,
            'total_cost' => (float) $this->total_cost,
            'created_by' => $this->whenLoaded('creator', function () {
                return $this->creator ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ] : null;
            }),
            'approved_by' => $this->whenLoaded('approver', function () {
                return $this->approver ? [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                ] : null;
            }),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'cancelled_by' => $this->whenLoaded('canceller', function () {
                return $this->canceller ? [
                    'id' => $this->canceller->id,
                    'name' => $this->canceller->name,
                ] : null;
            }),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'items' => WarehouseNoteItemResource::collection($this->whenLoaded('items')),
            'kardex_movements' => KardexMovementResource::collection($this->whenLoaded('kardexMovements')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
