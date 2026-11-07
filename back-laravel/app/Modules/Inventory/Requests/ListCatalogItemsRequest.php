<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use App\Modules\Inventory\Enums\CatalogItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCatalogItemsRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(CatalogItemType::class)],
            'category_id' => ['nullable', 'uuid'],
            'brand_id' => ['nullable', 'uuid'],
            'is_active' => ['nullable', 'boolean'],
            'is_sellable' => ['nullable', 'boolean'],
            'is_purchasable' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
