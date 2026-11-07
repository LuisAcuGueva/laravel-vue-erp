<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListBranchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
