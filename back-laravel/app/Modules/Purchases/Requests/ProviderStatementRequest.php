<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderStatementRequest extends FormRequest
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
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'currency_id' => ['nullable', 'uuid', Rule::exists('currencies', 'id')],
        ];
    }
}
