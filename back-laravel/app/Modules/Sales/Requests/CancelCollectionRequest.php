<?php

declare(strict_types=1);

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelCollectionRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Debe ingresar el motivo de anulación.',
            'reason.min' => 'El motivo de anulación debe tener al menos 5 caracteres.',
        ];
    }
}
