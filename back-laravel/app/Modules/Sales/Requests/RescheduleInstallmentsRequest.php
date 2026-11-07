<?php

declare(strict_types=1);

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleInstallmentsRequest extends FormRequest
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
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'installments.required' => 'Debe ingresar el nuevo cronograma de cuotas.',
            'installments.min' => 'Debe ingresar al menos una cuota.',
            'installments.*.due_date.required' => 'La fecha de vencimiento es obligatoria.',
            'installments.*.amount.required' => 'El importe de la cuota es obligatorio.',
        ];
    }
}
