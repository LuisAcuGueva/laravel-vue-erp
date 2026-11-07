<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseBankPeriodRequest extends FormRequest
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
            'statement_balance' => ['required', 'numeric', 'decimal:0,2'],
            'closed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
