<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashMovement
 */
class CashMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cash_session_id' => $this->cash_session_id,
            'box_id' => $this->box_id,
            'branch_id' => $this->branch_id,
            'direction' => $this->direction instanceof \BackedEnum
                ? $this->direction->value
                : (string) $this->direction,
            'concept_id' => $this->concept_id,
            'concept' => $this->whenLoaded('concept', fn () => $this->concept !== null ? [
                'id' => $this->concept->id,
                'code' => $this->concept->code,
                'name' => $this->concept->name,
                'direction' => $this->concept->direction instanceof \BackedEnum
                    ? $this->concept->direction->value
                    : (string) $this->concept->direction,
            ] : null),
            'payment_method_id' => $this->payment_method_id,
            'payment_method' => $this->whenLoaded('paymentMethod', fn () => $this->paymentMethod !== null ? [
                'id' => $this->paymentMethod->id,
                'code' => $this->paymentMethod->code,
                'name' => $this->paymentMethod->name,
                'settlement' => $this->paymentMethod->settlement instanceof \BackedEnum
                    ? $this->paymentMethod->settlement->value
                    : (string) $this->paymentMethod->settlement,
            ] : null),
            'affects_cash' => (bool) $this->affects_cash,
            'amount' => (float) $this->amount,
            'reference' => $this->reference,
            'description' => $this->description,
            'counterparty_name' => $this->counterparty_name,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'source_number' => $this->source_number,
            'reverses_id' => $this->reverses_id,
            'reversed_by' => $this->whenLoaded('reversedBy', fn () => $this->reversedBy !== null ? [
                'id' => $this->reversedBy->id,
                'direction' => $this->reversedBy->direction instanceof \BackedEnum
                    ? $this->reversedBy->direction->value
                    : (string) $this->reversedBy->direction,
                'description' => $this->reversedBy->description,
            ] : null),
            'is_manual' => (bool) $this->is_manual,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator !== null ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'moved_at' => $this->moved_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
