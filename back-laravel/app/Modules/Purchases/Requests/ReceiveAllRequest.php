<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use App\Modules\Purchases\Enums\ProviderDocumentType;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveAllRequest extends FormRequest
{
    /**
     * Pagar al contado al recibir exige además el permiso de registrar pagos.
     */
    public function authorize(): bool
    {
        if (empty($this->input('down_payment'))) {
            return true;
        }

        $user = $this->user();

        return $user instanceof User && $user->can('supplier_payments.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'provider_document_type' => ['required', Rule::enum(ProviderDocumentType::class)],
            'provider_document_number' => ['required_if:provider_document_type,invoice', 'nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'installments' => ['nullable', 'array', 'max:60'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'down_payment' => ['nullable', 'array'],
            'down_payment.amount' => ['required_with:down_payment', 'numeric', 'gt:0', 'decimal:0,2'],
            'down_payment.payment_method_id' => ['required_with:down_payment', 'uuid', 'exists:payment_methods,id'],
            'down_payment.cash_session_id' => ['nullable', 'uuid'],
            'down_payment.payment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'down_payment.reference' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider_document_number.required_if' => 'El número de comprobante es obligatorio cuando el tipo de documento es Factura.',
        ];
    }
}
