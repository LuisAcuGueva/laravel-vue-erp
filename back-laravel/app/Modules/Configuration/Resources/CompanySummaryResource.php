<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Resources;

use App\Modules\Configuration\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanySetting
 */
class CompanySummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CompanySetting $company */
        $company = $this->resource;

        return [
            'legal_name' => $company->legal_name,
            'trade_name' => $company->trade_name,
            'logo_url' => $company->logo_url,
            'default_currency_code' => $company->default_currency_code,
            'default_tax_rate' => (float) $company->default_tax_rate,
            'prices_include_tax' => (bool) $company->prices_include_tax,
        ];
    }
}
