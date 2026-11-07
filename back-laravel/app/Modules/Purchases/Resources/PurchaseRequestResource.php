<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseRequest
 */
class PurchaseRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseRequest $pr */
        $pr = $this->resource;

        return [
            'id' => $pr->id,
            'number' => $pr->number,
            'branch_id' => $pr->branch_id,
            'area_id' => $pr->area_id,
            'warehouse_id' => $pr->warehouse_id,
            'requested_by' => $pr->requested_by,
            'required_date' => $pr->required_date->format('Y-m-d'),
            'priority' => $pr->priority->value,
            'priority_label' => $pr->priority->label(),
            'status' => $pr->status->value,
            'status_label' => $pr->status->label(),
            'notes' => $pr->notes,
            'approved_by' => $pr->approved_by,
            'approved_at' => $pr->approved_at?->toIso8601String(),
            'rejected_at' => $pr->rejected_at?->toIso8601String(),
            'rejection_reason' => $pr->rejection_reason,
            'cancelled_at' => $pr->cancelled_at?->toIso8601String(),
            'cancel_reason' => $pr->cancel_reason,
            'items_count' => $this->whenCounted('items'),
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $pr->branch->id, 'code' => $pr->branch->code, 'name' => $pr->branch->name]),
            'area' => $this->whenLoaded('area', fn () => ['id' => $pr->area->id, 'name' => $pr->area->name]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => ['id' => $pr->warehouse->id, 'code' => $pr->warehouse->code, 'name' => $pr->warehouse->name]),
            'requester' => $this->whenLoaded('requester', fn () => ['id' => $pr->requester->id, 'name' => $pr->requester->name]),
            'approver' => $this->whenLoaded('approver', fn () => $pr->approver ? ['id' => $pr->approver->id, 'name' => $pr->approver->name] : null),
            'items' => PurchaseRequestItemResource::collection($this->whenLoaded('items')),
            'created_at' => $pr->created_at->toIso8601String(),
            'updated_at' => $pr->updated_at->toIso8601String(),
        ];
    }
}
