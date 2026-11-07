<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Provider
 */
class ProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Provider $provider */
        $provider = $this->resource;

        return [
            'id' => $provider->id,
            'document_type' => $provider->document_type->value,
            'document_number' => $provider->document_number,
            'business_name' => $provider->business_name,
            'trade_name' => $provider->trade_name,
            'address' => $provider->address,
            'ubigeo' => $provider->ubigeo,
            'email' => $provider->email,
            'phone' => $provider->phone,
            'website' => $provider->website,
            'default_currency_id' => $provider->default_currency_id,
            'credit_days' => $provider->credit_days,
            'notes' => $provider->notes,
            'is_active' => $provider->is_active,
            'default_currency' => $this->whenLoaded('defaultCurrency', fn () => $provider->defaultCurrency ? [
                'id' => $provider->defaultCurrency->id,
                'code' => $provider->defaultCurrency->code,
                'symbol' => $provider->defaultCurrency->symbol,
            ] : null),
            'primary_contact' => $this->whenLoaded('primaryContact', fn () => $provider->primaryContact
                ? new ProviderContactResource($provider->primaryContact)
                : null),
            'contacts' => ProviderContactResource::collection($this->whenLoaded('contacts')),
            'bank_accounts' => ProviderBankAccountResource::collection($this->whenLoaded('bankAccounts')),
            'created_at' => $provider->created_at?->toIso8601String(),
            'updated_at' => $provider->updated_at?->toIso8601String(),
        ];
    }
}
