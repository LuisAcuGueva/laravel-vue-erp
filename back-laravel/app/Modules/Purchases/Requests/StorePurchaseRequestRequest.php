<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use App\Modules\Purchases\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequestRequest extends FormRequest
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
            'area_id' => ['required', 'uuid', Rule::exists('areas', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouses', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'required_date' => ['required', 'date', 'after_or_equal:today'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.item_presentation_id' => [
                'nullable',
                'uuid',
                'required_without:items.*.description',
                Rule::exists('item_presentations', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'items.*.description' => ['nullable', 'string', 'max:255', 'required_without:items.*.item_presentation_id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required_date.after_or_equal' => 'La fecha requerida no puede ser anterior a hoy.',
            'items.required' => 'Agrega al menos un ítem al requerimiento.',
            'items.*.item_presentation_id.required_without' => 'Indica un artículo del catálogo o una descripción.',
            'items.*.description.required_without' => 'Indica un artículo del catálogo o una descripción.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor a 0.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $text = static fn (mixed $v): ?string => isset($v) && trim((string) $v) !== '' ? trim((string) $v) : null;

        $items = [];
        foreach ((array) $data['items'] as $item) {
            $items[] = [
                'item_presentation_id' => $item['item_presentation_id'] ?? null,
                'description' => $text($item['description'] ?? null),
                'quantity' => (string) $item['quantity'],
                'notes' => $text($item['notes'] ?? null),
            ];
        }

        return [
            'area_id' => $data['area_id'],
            'warehouse_id' => $data['warehouse_id'],
            'required_date' => $data['required_date'],
            'priority' => $data['priority'],
            'notes' => $text($data['notes'] ?? null),
            'items' => $items,
        ];
    }
}
