<?php

declare(strict_types=1);

namespace App\Modules\Clients\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientCreditRequest extends FormRequest
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
            'credit_limit' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'credit_days' => ['required', 'integer', 'between:0,365'],
        ];
    }

    /**
     * @return array{credit_limit: string, credit_days: int}
     */
    public function toAttributes(): array
    {
        return [
            'credit_limit' => (string) $this->validated('credit_limit'),
            'credit_days' => (int) $this->validated('credit_days'),
        ];
    }
}
