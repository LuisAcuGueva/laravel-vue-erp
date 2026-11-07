<?php

declare(strict_types=1);

namespace App\Modules\Sales\Requests;

use App\Modules\Sales\Enums\EInvoiceStatus;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Enums\SaleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSalesRequest extends FormRequest
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
            'document_type' => ['nullable', Rule::enum(SalesDocumentType::class)],
            'status' => ['nullable', Rule::enum(SaleStatus::class)],
            'client_id' => ['nullable', 'uuid'],
            'seller_id' => ['nullable', 'uuid'],
            'cash_session_id' => ['nullable', 'uuid'],
            'payment_condition' => ['nullable', Rule::enum(PaymentCondition::class)],
            'einvoice_status' => ['nullable', Rule::enum(EInvoiceStatus::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
