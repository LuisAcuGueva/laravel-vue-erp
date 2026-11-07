<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('below_min')) {
            $this->merge(['below_min' => $this->boolean('below_min')]);
        }
        if ($this->has('include_zero')) {
            $this->merge(['include_zero' => $this->boolean('include_zero')]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'below_min' => ['nullable', 'boolean'],
            'include_zero' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
