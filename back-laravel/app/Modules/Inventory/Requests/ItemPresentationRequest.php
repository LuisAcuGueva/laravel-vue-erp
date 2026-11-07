<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemPresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('barcode') && is_string($this->input('barcode'))) {
            $barcode = trim((string) $this->input('barcode'));
            $this->merge(['barcode' => $barcode !== '' ? $barcode : null]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var string|null $presentationId */
        $presentationId = $this->route('presentationId');

        return [
            'name' => [
                'required',
                'string',
                'max:80',
            ],
            'unit_id' => [
                'required',
                'uuid',
                Rule::exists('units', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'factor' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,4',
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('item_presentations', 'barcode')
                    ->whereNull('deleted_at')
                    ->ignore($presentationId),
            ],
            'is_default' => [
                'sometimes',
                'boolean',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'barcode.unique' => 'Este código de barras ya pertenece a otra presentación.',
            'unit_id.exists' => 'La unidad seleccionada no existe o no está activa.',
            'factor.gt' => 'El factor de conversión debe ser mayor a cero.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        $attrs = [
            'name' => trim((string) $data['name']),
            'unit_id' => $data['unit_id'],
            'factor' => (float) $data['factor'],
        ];

        if (array_key_exists('barcode', $data)) {
            $attrs['barcode'] = $data['barcode'] !== null && trim((string) $data['barcode']) !== ''
                ? trim((string) $data['barcode'])
                : null;
        }

        if (array_key_exists('is_default', $data)) {
            $attrs['is_default'] = (bool) $data['is_default'];
        }

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        return $attrs;
    }
}
