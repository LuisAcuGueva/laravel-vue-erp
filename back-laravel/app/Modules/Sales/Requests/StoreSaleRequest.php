<?php

declare(strict_types=1);

namespace App\Modules\Sales\Requests;

use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Sales\Enums\PaymentCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
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
        $isCash = $this->input('payment_condition') === PaymentCondition::Cash->value;

        return [
            'document_series_id' => ['required', 'uuid'],
            'cash_session_id' => ['nullable', 'uuid'],
            'warehouse_id' => ['nullable', 'uuid'],
            'client_id' => ['required', 'uuid'],
            'seller_id' => ['nullable', 'uuid'],
            'issue_date' => ['nullable', 'date'],
            'currency_id' => ['required', 'uuid'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'price_type' => ['nullable', 'string', Rule::in([PriceType::Retail->value, PriceType::Wholesale->value])],
            'prices_include_tax' => ['nullable', 'boolean'],
            'global_discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'quotation_id' => ['nullable', 'uuid'],
            'payment_condition' => ['required', Rule::enum(PaymentCondition::class)],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],

            // Cuotas (Crédito)
            'installments' => ['nullable', 'array'],
            'installments.*.due_date' => ['required_with:installments', 'date'],
            'installments.*.amount' => ['required_with:installments', 'numeric', 'gt:0', 'decimal:0,2'],
            'installments_count' => ['nullable', 'integer', 'between:1,60'],
            'installment_interval_days' => ['nullable', 'integer', 'between:1,365'],

            // Líneas
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.item_presentation_id' => ['nullable', 'uuid'],
            'items.*.description' => ['nullable', 'string', 'max:255', 'required_without:items.*.item_presentation_id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_type' => ['nullable', 'string'],

            // Pagos
            'payments' => [$isCash ? 'required' : 'nullable', 'array'],
            'payments.*.payment_method_id' => ['required', 'uuid'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'payments.*.received_amount' => ['nullable', 'numeric'],
            'payments.*.reference' => ['nullable', 'string', 'max:60'],
            'payments.*.cash_session_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_series_id.required' => 'Debe seleccionar una serie de comprobante.',
            'client_id.required' => 'Debe seleccionar un cliente.',
            'currency_id.required' => 'Debe seleccionar la moneda del comprobante.',
            'items.required' => 'Debe agregar al menos un ítem a la venta.',
            'payments.required' => 'Debe registrar al menos un método de pago para ventas al contado.',
        ];
    }
}
