<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentMethod
 */
class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PaymentMethod $method */
        $method = $this->resource;

        return [
            'id' => $method->id,
            'code' => $method->code,
            'name' => $method->name,
            'kind' => $method->kind instanceof \BackedEnum ? $method->kind->value : $method->kind,
            'settlement' => $method->settlement instanceof \BackedEnum ? $method->settlement->value : $method->settlement,
            'bank_account_id' => $method->bank_account_id,
            'requires_reference' => $method->requires_reference,
            'sunat_code' => $method->sunat_code,
            'sort_order' => $method->sort_order,
            'is_system' => $method->is_system,
            'is_active' => $method->is_active,
            'bank_account' => BankAccountResource::make($this->whenLoaded('bankAccount')),
            'created_at' => $method->created_at?->toIso8601String(),
            'updated_at' => $method->updated_at?->toIso8601String(),
        ];
    }
}
