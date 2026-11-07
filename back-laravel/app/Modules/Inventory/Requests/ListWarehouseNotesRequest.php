<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use App\Modules\Inventory\Enums\WarehouseNoteStatus;
use App\Modules\Inventory\Enums\WarehouseNoteType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListWarehouseNotesRequest extends FormRequest
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
            'type' => [
                'nullable',
                Rule::enum(WarehouseNoteType::class),
            ],
            'status' => [
                'nullable',
                Rule::enum(WarehouseNoteStatus::class),
            ],
            'warehouse_id' => [
                'nullable',
                'string',
                'uuid',
            ],
            'reason_id' => [
                'nullable',
                'string',
                'uuid',
            ],
            'date_from' => [
                'nullable',
                'date',
            ],
            'date_to' => [
                'nullable',
                'date',
            ],
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
