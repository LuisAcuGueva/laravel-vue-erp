<?php

declare(strict_types=1);

namespace App\Modules\Sales\Requests;

use App\Modules\Inventory\Enums\PriceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid'],
            'contact_id' => ['nullable', 'uuid'],
            'seller_id' => ['nullable', 'uuid'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency_id' => ['required', 'uuid'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'price_type' => ['nullable', 'string', Rule::in([PriceType::Retail->value, PriceType::Wholesale->value])],
            'prices_include_tax' => ['nullable', 'boolean'],
            'global_discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'delivery_time' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.item_presentation_id' => ['nullable', 'uuid'],
            'items.*.description' => ['nullable', 'string', 'max:255', 'required_without:items.*.item_presentation_id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4', 'required_without:items.*.item_presentation_id'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'El cliente es obligatorio.',
            'issue_date.required' => 'La fecha de emisión es obligatoria.',
            'valid_until.after_or_equal' => 'La fecha de validez debe ser igual o posterior a la fecha de emisión.',
            'currency_id.required' => 'La moneda es obligatoria.',
            'items.required' => 'Debe agregar al menos una línea a la cotización.',
            'items.min' => 'Debe agregar al menos una línea a la cotización.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor a cero.',
            'items.*.unit_price.required_without' => 'El precio unitario es obligatorio para líneas de texto libre.',
            'items.*.description.required_without' => 'La descripción es obligatoria cuando no se selecciona un producto.',
        ];
    }
}
