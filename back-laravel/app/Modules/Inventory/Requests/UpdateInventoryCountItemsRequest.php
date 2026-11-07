<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryCountItemsRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.catalog_item_id' => ['required', 'uuid', 'exists:catalog_items,id'],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return list<array{catalog_item_id: string, counted_quantity: float}>
     */
    public function items(): array
    {
        /** @var list<array{catalog_item_id: string, counted_quantity: float}> $items */
        $items = $this->validated('items');

        return $items;
    }
}
