<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use App\Modules\Purchases\Enums\SupplierInvoiceKind;
use App\Modules\Purchases\Enums\SupplierInvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSupplierInvoicesRequest extends FormRequest
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
            'kind' => ['nullable', Rule::enum(SupplierInvoiceKind::class)],
            'status' => ['nullable', Rule::enum(SupplierInvoiceStatus::class)],
            'provider_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
