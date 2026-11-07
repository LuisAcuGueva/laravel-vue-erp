<?php

declare(strict_types=1);

namespace App\Modules\Audit\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListAuditLogsRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'log_name' => ['nullable', 'string', 'max:50'],
            'event' => ['nullable', 'string', 'max:50'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'uuid'],
            'causer_id' => ['nullable', 'uuid'],
            'branch_id' => ['nullable', 'uuid'],
            'session_id' => ['nullable', 'uuid'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ];
    }
}
