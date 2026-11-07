<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderPriceRequest extends FormRequest
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
            'item_presentation_id' => ['required', 'uuid', Rule::exists('item_presentations', 'id')->whereNull('deleted_at')],
            'currency_id' => ['required', 'uuid', Rule::exists('currencies', 'id')->whereNull('deleted_at')],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $data = $this->validated();

        return [
            'item_presentation_id' => $data['item_presentation_id'],
            'currency_id' => $data['currency_id'],
            'price' => $data['price'],
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'] ?? null,
            'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
        ];
    }
}
