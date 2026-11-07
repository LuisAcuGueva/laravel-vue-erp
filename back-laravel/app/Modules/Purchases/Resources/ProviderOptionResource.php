<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Provider
 */
class ProviderOptionResource extends JsonResource
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
            'default_currency_id' => $provider->default_currency_id,
            'credit_days' => $provider->credit_days,
        ];
    }
}
