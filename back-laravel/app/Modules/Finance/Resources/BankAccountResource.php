<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\BankAccount;
use App\Modules\Maintenance\Resources\BranchResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankAccount
 */
class BankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BankAccount $account */
        $account = $this->resource;

        return [
            'id' => $account->id,
            'bank_id' => $account->bank_id,
            'currency_id' => $account->currency_id,
            'branch_id' => $account->branch_id,
            'alias' => $account->alias,
            'account_number' => $account->account_number,
            'cci' => $account->cci,
            'account_type' => $account->account_type instanceof \BackedEnum ? $account->account_type->value : $account->account_type,
            'holder_name' => $account->holder_name,
            'balance' => $account->balance,
            'is_active' => $account->is_active,
            'display_name' => $account->display_name,
            'bank' => BankResource::make($this->whenLoaded('bank')),
            'currency' => CurrencyResource::make($this->whenLoaded('currency')),
            'branch' => BranchResource::make($this->whenLoaded('branch')),
            'open_period' => BankPeriodResource::make($this->whenLoaded('openPeriod')),
            'created_at' => $account->created_at?->toIso8601String(),
            'updated_at' => $account->updated_at?->toIso8601String(),
        ];
    }
}
