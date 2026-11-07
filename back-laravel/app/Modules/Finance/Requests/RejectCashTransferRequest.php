<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectCashTransferRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'El motivo es obligatorio.',
            'reason.min' => 'El motivo debe tener al menos 3 caracteres.',
            'reason.max' => 'El motivo no puede exceder los 500 caracteres.',
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
