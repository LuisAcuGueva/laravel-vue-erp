<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListBoxesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El frontend envía `is_active` como literal "true"/"false" en el query string;
     * se normaliza a booleano real antes de que la regla `boolean` (estricta) lo valide.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'string', 'uuid'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
