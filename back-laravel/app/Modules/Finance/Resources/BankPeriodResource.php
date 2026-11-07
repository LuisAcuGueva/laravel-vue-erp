<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\BankPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankPeriod
 */
class BankPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_account_id' => $this->bank_account_id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'opened_at' => $this->opened_at->toDateString(),
            'opening_balance' => (float) $this->opening_balance,
            'closed_at' => $this->closed_at?->toDateString(),
            'closed_by' => $this->closed_by,
            'closed_by_name' => $this->closedByUser?->name,
            'expected_balance' => $this->expected_balance !== null ? (float) $this->expected_balance : null,
            'statement_balance' => $this->statement_balance !== null ? (float) $this->statement_balance : null,
            'difference' => $this->difference !== null ? (float) $this->difference : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
