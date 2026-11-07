<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockLimitsRequest extends FormRequest
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
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'catalog_item_id' => ['required', 'uuid', 'exists:catalog_items,id'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'gte:min_stock'],
        ];
    }
}
