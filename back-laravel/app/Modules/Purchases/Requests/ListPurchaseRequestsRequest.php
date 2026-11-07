<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use App\Modules\Purchases\Enums\Priority;
use App\Modules\Purchases\Enums\PurchaseRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPurchaseRequestsRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::enum(PurchaseRequestStatus::class)],
            'priority' => ['sometimes', 'nullable', Rule::enum(Priority::class)],
            'area_id' => ['sometimes', 'nullable', 'uuid'],
            'mine' => ['sometimes', 'nullable'],
        ];
    }
}
