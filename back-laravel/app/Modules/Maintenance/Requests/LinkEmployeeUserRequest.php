<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkEmployeeUserRequest extends FormRequest
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
            'user_id' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Debe seleccionar un usuario para vincular.',
            'user_id.exists' => 'El usuario seleccionado no existe o está eliminado.',
        ];
    }

    public function userId(): string
    {
        return (string) $this->validated('user_id');
    }
}
