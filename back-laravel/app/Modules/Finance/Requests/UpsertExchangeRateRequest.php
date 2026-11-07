<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'currency_id' => [
                'required',
                'uuid',
                'exists:currencies,id',
            ],
            'date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'buy_rate' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,4',
            ],
            'sell_rate' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,4',
                'gte:buy_rate',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sell_rate.gte' => 'El tipo de cambio de venta debe ser mayor o igual al de compra',
        ];
    }
}
