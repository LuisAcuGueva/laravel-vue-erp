<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use App\Modules\Inventory\Enums\WarehouseReasonType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListWarehouseReasonsRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(WarehouseReasonType::class)],
            'is_active' => ['nullable', 'boolean'],
            'include_system' => ['nullable', 'boolean'],
        ];
    }
}
