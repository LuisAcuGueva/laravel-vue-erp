<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWarehouseNoteRequest extends FormRequest
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
            'target_warehouse_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('warehouses', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'reason_id' => [
                'required',
                'string',
                'uuid',
                Rule::exists('warehouse_reasons', 'id')->where('is_active', true),
            ],
            'document_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'reference_document' => [
                'nullable',
                'string',
                'max:60',
            ],
            'observation' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],
            'items.*.item_presentation_id' => [
                'required',
                'string',
                'uuid',
                Rule::exists('item_presentations', 'id')->where('is_active', true),
            ],
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,4',
            ],
            'items.*.unit_cost' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,4',
            ],
            'items.*.notes' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var list<array<string, mixed>> $rawItems */
            $rawItems = (array) $this->input('items', []);
            $presentations = collect($rawItems)->pluck('item_presentation_id')->filter();

            if ($presentations->count() !== $presentations->unique()->count()) {
                $validator->errors()->add('items', 'No se pueden duplicar presentaciones de artículos en las líneas de la nota.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        /** @var list<array<string, mixed>> $items */
        $items = (array) ($data['items'] ?? []);

        return [
            'target_warehouse_id' => ! empty($data['target_warehouse_id']) ? (string) $data['target_warehouse_id'] : null,
            'reason_id' => (string) $data['reason_id'],
            'document_date' => (string) $data['document_date'],
            'reference_document' => ! empty($data['reference_document']) ? trim((string) $data['reference_document']) : null,
            'observation' => ! empty($data['observation']) ? trim((string) $data['observation']) : null,
            'items' => array_map(function (array $line) {
                return [
                    'item_presentation_id' => (string) $line['item_presentation_id'],
                    'quantity' => (float) $line['quantity'],
                    'unit_cost' => isset($line['unit_cost']) ? (float) $line['unit_cost'] : null,
                    'notes' => ! empty($line['notes']) ? trim((string) $line['notes']) : null,
                ];
            }, $items),
        ];
    }
}
