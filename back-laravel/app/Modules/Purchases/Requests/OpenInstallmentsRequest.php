<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenInstallmentsRequest extends FormRequest
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
            'provider_id' => ['nullable', 'uuid'],
            'currency_id' => ['required', 'uuid'],
        ];
    }
}
