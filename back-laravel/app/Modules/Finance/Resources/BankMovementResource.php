<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\BankMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankMovement
 */
class BankMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_account_id' => $this->bank_account_id,
            'bank_period_id' => $this->bank_period_id,
            'branch_id' => $this->branch_id,
            'direction' => $this->direction->value,
            'concept' => [
                'id' => $this->concept->id,
                'code' => $this->concept->code,
                'name' => $this->concept->name,
            ],
            'payment_method' => $this->paymentMethod !== null ? [
                'id' => $this->paymentMethod->id,
                'code' => $this->paymentMethod->code,
                'name' => $this->paymentMethod->name,
            ] : null,
            'amount' => (float) $this->amount,
            'balance_after' => (float) $this->balance_after,
            'operation_number' => $this->operation_number,
            'movement_date' => $this->movement_date->toDateString(),
            'description' => $this->description,
            'counterparty_name' => $this->counterparty_name,
            'voucher_url' => $this->voucher_path !== null
                ? url("/api/v1/finance/bank-movements/{$this->id}/voucher")
                : null,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'source_number' => $this->source_number,
            'reverses_id' => $this->reverses_id,
            'is_manual' => $this->is_manual,
            'is_reversed' => $this->reversal !== null,
            'creator_name' => $this->creator?->name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
