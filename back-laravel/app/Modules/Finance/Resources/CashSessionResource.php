<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashSession
 */
class CashSessionResource extends JsonResource
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $summaryData = null;

    /**
     * @param  array<string, mixed>|null  $summaryData
     */
    public function withSummary(?array $summaryData): self
    {
        $this->summaryData = $summaryData;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'box_id' => $this->box_id,
            'box' => $this->whenLoaded('box', fn () => $this->box !== null ? [
                'id' => $this->box->id,
                'code' => $this->box->code,
                'name' => $this->box->name,
            ] : null),
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch !== null ? [
                'id' => $this->branch->id,
                'code' => $this->branch->code,
                'name' => $this->branch->name,
            ] : null),
            'status' => $this->status instanceof \BackedEnum
                ? $this->status->value
                : (string) $this->status,
            'opened_by' => $this->opened_by,
            'opened_at' => $this->opened_at->toIso8601String(),
            'opening_amount' => (float) $this->opening_amount,
            'opening_notes' => $this->opening_notes,
            'closed_by' => $this->closed_by,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'expected_cash' => $this->expected_cash !== null ? (float) $this->expected_cash : null,
            'counted_cash' => $this->counted_cash !== null ? (float) $this->counted_cash : null,
            'difference' => $this->difference !== null ? (float) $this->difference : null,
            'closing_notes' => $this->closing_notes,
            'review_status' => $this->review_status instanceof \BackedEnum
                ? $this->review_status->value
                : ($this->review_status !== null ? (string) $this->review_status : null),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_notes' => $this->review_notes,
            'opener' => $this->whenLoaded('opener', fn () => $this->opener !== null ? [
                'id' => $this->opener->id,
                'name' => $this->opener->name,
            ] : null),
            'closer' => $this->whenLoaded('closer', fn () => $this->closer !== null ? [
                'id' => $this->closer->id,
                'name' => $this->closer->name,
            ] : null),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer !== null ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null),
            'count_details' => $this->whenLoaded('countDetails', fn () => $this->countDetails->map(fn ($d): array => [
                'id' => $d->id,
                'denomination' => (float) $d->denomination,
                'quantity' => (int) $d->quantity,
                'subtotal' => (float) $d->subtotal,
            ])),
            'summary' => $this->when($this->summaryData !== null, $this->summaryData),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
