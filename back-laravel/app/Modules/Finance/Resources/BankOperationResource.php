<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\BankOperation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankOperation
 */
class BankOperationResource extends JsonResource
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
            'bank_account' => [
                'id' => $this->bankAccount->id,
                'alias' => $this->bankAccount->alias,
                'account_number' => $this->bankAccount->account_number,
                'currency' => $this->bankAccount->currency !== null ? [
                    'id' => $this->bankAccount->currency->id,
                    'code' => $this->bankAccount->currency->code,
                    'symbol' => $this->bankAccount->currency->symbol,
                ] : null,
            ],
            'target_bank_account' => $this->targetBankAccount !== null ? [
                'id' => $this->targetBankAccount->id,
                'alias' => $this->targetBankAccount->alias,
                'account_number' => $this->targetBankAccount->account_number,
                'currency' => $this->targetBankAccount->currency !== null ? [
                    'id' => $this->targetBankAccount->currency->id,
                    'code' => $this->targetBankAccount->currency->code,
                    'symbol' => $this->targetBankAccount->currency->symbol,
                ] : null,
            ] : null,
            'cash_session' => $this->cashSession !== null ? [
                'id' => $this->cashSession->id,
                'box' => $this->cashSession->box !== null ? [
                    'id' => $this->cashSession->box->id,
                    'name' => $this->cashSession->box->name,
                ] : null,
            ] : null,
            'amount' => (float) $this->amount,
            'target_amount' => $this->target_amount !== null ? (float) $this->target_amount : null,
            'exchange_rate' => $this->exchange_rate !== null ? (float) $this->exchange_rate : null,
            'operation_number' => $this->operation_number,
            'operation_date' => $this->operation_date->toDateString(),
            'notes' => $this->notes,
            'voucher_url' => $this->voucher_path !== null
                ? url("/api/v1/finance/bank-operations/{$this->id}/voucher")
                : null,
            'creator' => $this->creator !== null ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'canceller' => $this->canceller !== null ? [
                'id' => $this->canceller->id,
                'name' => $this->canceller->name,
            ] : null,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
