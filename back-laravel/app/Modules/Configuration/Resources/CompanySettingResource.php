<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Resources;

use App\Modules\Configuration\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanySetting
 */
class CompanySettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CompanySetting $company */
        $company = $this->resource;

        return [
            'id' => $company->id,
            'legal_name' => $company->legal_name,
            'trade_name' => $company->trade_name,
            'legal_representative' => $company->legal_representative,
            'ruc' => $company->ruc,
            'address' => $company->address,
            'ubigeo' => $company->ubigeo,
            'phone' => $company->phone,
            'email' => $company->email,
            'website' => $company->website,
            'logo_url' => $company->logo_url,
            'default_currency_code' => $company->default_currency_code,
            'default_tax_rate' => (float) $company->default_tax_rate,
            'prices_include_tax' => (bool) $company->prices_include_tax,
            'terms_conditions' => $company->terms_conditions,
            'warranty_policy' => $company->warranty_policy,
            'social_links' => $company->social_links,
            'created_at' => $company->created_at?->toIso8601String(),
            'updated_at' => $company->updated_at?->toIso8601String(),
        ];
    }
}
