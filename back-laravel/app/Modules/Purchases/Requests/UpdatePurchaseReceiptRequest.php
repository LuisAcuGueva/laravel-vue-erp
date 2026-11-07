<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use App\Modules\Purchases\Enums\ProviderDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseReceiptRequest extends FormRequest
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
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
            'receipt_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'provider_document_type' => ['sometimes', 'required', Rule::enum(ProviderDocumentType::class)],
            'provider_document_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'uuid', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
