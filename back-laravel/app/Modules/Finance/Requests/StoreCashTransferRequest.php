<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashTransferRequest extends FormRequest
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
            'source_session_id' => [
                'required',
                'uuid',
                Rule::exists('cash_sessions', 'id'),
            ],
            'target_box_id' => [
                'required',
                'uuid',
                Rule::exists('boxes', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_session_id.required' => 'La sesión de caja de origen es obligatoria.',
            'target_box_id.required' => 'La caja de destino es obligatoria.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.gt' => 'El monto debe ser mayor a 0.',
            'reason.required' => 'El motivo es obligatorio.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
            'reason.max' => 'El motivo no puede exceder los 255 caracteres.',
        ];
    }
}
