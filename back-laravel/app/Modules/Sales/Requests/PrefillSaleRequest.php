<?php

declare(strict_types=1);

namespace App\Modules\Sales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrefillSaleRequest extends FormRequest
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
            'quotation_id' => ['required', 'string', 'uuid'],
        ];
    }
}
