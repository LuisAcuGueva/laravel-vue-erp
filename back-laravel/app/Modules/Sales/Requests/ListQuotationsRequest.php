<?php

declare(strict_types=1);

namespace App\Modules\Sales\Requests;

use App\Modules\Sales\Enums\QuotationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListQuotationsRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in(array_column(QuotationStatus::cases(), 'value'))],
            'client_id' => ['nullable', 'uuid'],
            'seller_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
