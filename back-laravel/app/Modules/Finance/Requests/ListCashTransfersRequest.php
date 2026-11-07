<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\CashTransferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCashTransfersRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::enum(CashTransferStatus::class)],
            'box_id' => ['sometimes', 'nullable', 'uuid'],
            'direction' => ['sometimes', 'nullable', Rule::in(['sent', 'received'])],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
