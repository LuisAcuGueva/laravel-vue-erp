<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListProvidersRequest extends FormRequest
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
            'is_active' => ['sometimes', 'nullable'],
        ];
    }
}
