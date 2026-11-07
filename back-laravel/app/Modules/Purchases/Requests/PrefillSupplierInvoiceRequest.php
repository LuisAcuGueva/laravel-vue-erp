<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrefillSupplierInvoiceRequest extends FormRequest
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
            'receipt_ids' => ['required', 'array', 'min:1', 'max:50'],
            'receipt_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
