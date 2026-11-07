<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\ProviderBankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderBankAccount
 */
class ProviderBankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProviderBankAccount $account */
        $account = $this->resource;

        return [
            'id' => $account->id,
            'bank_id' => $account->bank_id,
            'currency_id' => $account->currency_id,
            'account_number' => $account->account_number,
            'cci' => $account->cci,
            'is_detraction_account' => $account->is_detraction_account,
            'bank' => $this->whenLoaded('bank', fn () => [
                'id' => $account->bank->id,
                'name' => $account->bank->name,
                'short_name' => $account->bank->short_name,
            ]),
            'currency' => $this->whenLoaded('currency', fn () => [
                'id' => $account->currency->id,
                'code' => $account->currency->code,
                'symbol' => $account->currency->symbol,
            ]),
        ];
    }
}
