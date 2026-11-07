<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LowStockRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouses', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'area_id' => ['required', 'uuid', Rule::exists('areas', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'category_id' => ['nullable', 'uuid', Rule::exists('categories', 'id')],
        ];
    }
}
