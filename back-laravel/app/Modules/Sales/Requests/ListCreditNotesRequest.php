<?php

declare(strict_types=1);

namespace App\Modules\Sales\Requests;

use App\Modules\Sales\Enums\CreditNoteReason;
use App\Modules\Sales\Enums\CreditNoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCreditNotesRequest extends FormRequest
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
            'sale_id' => ['nullable', 'uuid', 'exists:sales,id'],
            'client_id' => ['nullable', 'uuid', 'exists:clients,id'],
            'reason_code' => ['nullable', 'string', Rule::enum(CreditNoteReason::class)],
            'status' => ['nullable', 'string', Rule::enum(CreditNoteStatus::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
