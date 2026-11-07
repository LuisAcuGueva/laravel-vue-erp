<?php

declare(strict_types=1);

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionRequest extends FormRequest
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
            'currency_id' => ['required', 'uuid'],
            'payment_date' => ['nullable', 'date'],
            'collection_date' => ['nullable', 'date'],

            // Método y montos
            'payment_method_id' => ['nullable', 'uuid', 'required_without:payments'],
            'cash_session_id' => ['nullable', 'uuid'],
            'bank_account_id' => ['nullable', 'uuid'],
            'amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'required_without_all:total_amount,payments'],
            'total_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'auto_allocate' => ['nullable', 'boolean'],

            // Pagos múltiples opcionales
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['required_with:payments', 'uuid'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0', 'decimal:0,2'],
            'payments.*.cash_session_id' => ['nullable', 'uuid'],
            'payments.*.bank_account_id' => ['nullable', 'uuid'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],

            // Asignaciones
            'allocations' => ['nullable', 'array'],
            'allocations.*.receivable_installment_id' => ['nullable', 'uuid'],
            'allocations.*.installment_id' => ['nullable', 'uuid'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'Debe seleccionar un cliente.',
            'currency_id.required' => 'Debe indicar la moneda de la cobranza.',
            'payment_method_id.required_without' => 'Debe seleccionar un método de pago.',
            'amount.required_without_all' => 'Debe indicar el monto total cobrado.',
        ];
    }
}
