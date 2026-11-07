<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\CashTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashTransfer
 */
class CashTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'amount' => $this->amount,
            'reason' => $this->reason,
            'source_session_id' => $this->source_session_id,
            'source_box_id' => $this->source_box_id,
            'source_branch_id' => $this->source_branch_id,
            'source_box' => $this->whenLoaded('sourceBox', fn () => $this->sourceBox !== null ? [
                'id' => $this->sourceBox->id,
                'code' => $this->sourceBox->code,
                'name' => $this->sourceBox->name,
            ] : null),
            'source_branch' => $this->whenLoaded('sourceBranch', fn () => $this->sourceBranch !== null ? [
                'id' => $this->sourceBranch->id,
                'code' => $this->sourceBranch->code,
                'name' => $this->sourceBranch->name,
            ] : null),
            'source_session' => $this->whenLoaded('sourceSession', fn () => $this->sourceSession !== null ? [
                'id' => $this->sourceSession->id,
                'number' => $this->sourceSession->number,
            ] : null),
            'target_box_id' => $this->target_box_id,
            'target_branch_id' => $this->target_branch_id,
            'target_session_id' => $this->target_session_id,
            'target_box' => $this->whenLoaded('targetBox', fn () => $this->targetBox !== null ? [
                'id' => $this->targetBox->id,
                'code' => $this->targetBox->code,
                'name' => $this->targetBox->name,
            ] : null),
            'target_branch' => $this->whenLoaded('targetBranch', fn () => $this->targetBranch !== null ? [
                'id' => $this->targetBranch->id,
                'code' => $this->targetBranch->code,
                'name' => $this->targetBranch->name,
            ] : null),
            'target_session' => $this->whenLoaded('targetSession', fn () => $this->targetSession !== null ? [
                'id' => $this->targetSession->id,
                'number' => $this->targetSession->number,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator !== null ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver !== null ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejecter' => $this->whenLoaded('rejecter', fn () => $this->rejecter !== null ? [
                'id' => $this->rejecter->id,
                'name' => $this->rejecter->name,
            ] : null),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'canceller' => $this->whenLoaded('canceller', fn () => $this->canceller !== null ? [
                'id' => $this->canceller->id,
                'name' => $this->canceller->name,
            ] : null),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'movements' => $this->whenLoaded('cashMovements', fn () => CashMovementResource::collection($this->cashMovements)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
