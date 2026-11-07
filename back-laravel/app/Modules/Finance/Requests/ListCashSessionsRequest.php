<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListCashSessionsRequest extends FormRequest
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
            'box_id' => ['nullable', 'string', 'uuid'],
            'branch_id' => ['nullable', 'string', 'uuid'],
            'status' => ['nullable', 'string', 'in:open,closed'],
            'review_status' => ['nullable', 'string', 'in:pending,approved,rejected'],
            'opened_by' => ['nullable', 'string', 'uuid'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
