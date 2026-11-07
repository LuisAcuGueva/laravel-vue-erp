<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use App\Modules\Purchases\Enums\SupplierInvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPayablesRequest extends FormRequest
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
            'provider_id' => ['nullable', 'uuid'],
            'currency_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::enum(SupplierInvoiceStatus::class)],
            'overdue' => ['nullable', 'boolean'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date', 'after_or_equal:due_from'],
            'as_of' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $data['overdue'] = $this->boolean('overdue');

        return $data;
    }
}
