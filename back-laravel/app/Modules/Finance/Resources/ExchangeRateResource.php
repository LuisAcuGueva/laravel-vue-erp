<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExchangeRate
 */
class ExchangeRateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ExchangeRate $rate */
        $rate = $this->resource;

        return [
            'id' => $rate->id,
            'currency_id' => $rate->currency_id,
            'date' => $rate->date instanceof \DateTimeInterface ? $rate->date->format('Y-m-d') : (string) $rate->date,
            'buy_rate' => $rate->buy_rate,
            'sell_rate' => $rate->sell_rate,
            'created_by' => $rate->created_by,
            'currency' => CurrencyResource::make($this->whenLoaded('currency')),
            'creator' => $this->whenLoaded('creator', function () use ($rate) {
                return $rate->creator ? [
                    'id' => $rate->creator->id,
                    'name' => $rate->creator->name,
                    'email' => $rate->creator->email,
                ] : null;
            }),
            'created_at' => $rate->created_at?->toIso8601String(),
            'updated_at' => $rate->updated_at?->toIso8601String(),
        ];
    }
}
