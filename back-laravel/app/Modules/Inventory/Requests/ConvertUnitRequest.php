<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertUnitRequest extends FormRequest
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
            'quantity' => [
                'required',
                'numeric',
            ],
            'from_unit_id' => [
                'required',
                'string',
                'uuid',
                Rule::exists('units', 'id')
                    ->whereNull('deleted_at'),
            ],
            'to_unit_id' => [
                'required',
                'string',
                'uuid',
                Rule::exists('units', 'id')
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
