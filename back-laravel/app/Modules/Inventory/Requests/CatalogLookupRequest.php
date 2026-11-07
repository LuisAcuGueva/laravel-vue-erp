<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogLookupRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'context' => ['required', 'string', 'in:sale,purchase,stock'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'warehouse_id' => ['nullable', 'uuid'],
        ];
    }
}
