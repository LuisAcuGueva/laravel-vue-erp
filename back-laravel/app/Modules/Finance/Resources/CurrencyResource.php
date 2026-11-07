<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Currency
 */
class CurrencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Currency $currency */
        $currency = $this->resource;

        return [
            'id' => $currency->id,
            'code' => $currency->code,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'sunat_code' => $currency->sunat_code,
            'decimals' => $currency->decimals,
            'is_base' => $currency->is_base,
            'is_active' => $currency->is_active,
            'created_at' => $currency->created_at?->toIso8601String(),
            'updated_at' => $currency->updated_at?->toIso8601String(),
        ];
    }
}
