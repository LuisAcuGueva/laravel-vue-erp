<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use App\Modules\Purchases\Enums\ProviderDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseReceiptRequest extends FormRequest
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
            'purchase_order_id' => ['required', 'uuid', 'exists:purchase_orders,id'],
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'provider_document_type' => ['required', Rule::enum(ProviderDocumentType::class)],
            'provider_document_number' => ['required_if:provider_document_type,invoice', 'nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'uuid', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
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
