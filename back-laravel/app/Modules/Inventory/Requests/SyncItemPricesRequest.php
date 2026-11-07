<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use App\Modules\Inventory\Enums\PriceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncItemPricesRequest extends FormRequest
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
            'prices' => [
                'present',
                'array',
            ],
            'prices.*.presentation_id' => [
                'required',
                'uuid',
            ],
            'prices.*.price_type' => [
                'required',
                Rule::enum(PriceType::class),
            ],
            'prices.*.price' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,4',
            ],
            'prices.*.min_quantity' => [
                'nullable',
                'numeric',
                'min:1',
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $prices = (array) $this->input('prices', []);
                $seen = [];

                foreach ($prices as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $presId = $item['presentation_id'] ?? '';
                    $type = $item['price_type'] ?? '';
                    $key = "{$presId}:{$type}";

                    if (isset($seen[$key])) {
                        $validator->errors()->add(
                            "prices.{$index}.price_type",
                            'No se puede repetir el mismo tipo de precio para una misma presentación.'
                        );
                    }
                    $seen[$key] = true;
                }
            },
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toPrices(): array
    {
        return (array) $this->validated()['prices'];
    }
}
