<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListEmployeesRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'branch_id' => ['sometimes', 'nullable', 'uuid'],
            'area_id' => ['sometimes', 'nullable', 'uuid'],
            'is_active' => ['sometimes', 'nullable'],
            'has_user' => ['sometimes', 'nullable'],
        ];
    }
}
