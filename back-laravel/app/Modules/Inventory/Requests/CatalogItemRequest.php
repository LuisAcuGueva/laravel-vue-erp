<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use App\Modules\Inventory\Enums\CatalogItemType;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Inventory\Enums\TaxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $code = trim((string) $this->input('code'));
            $this->merge(['code' => $code !== '' ? strtoupper($code) : null]);
        }

        if ($this->has('brand_id') && ($this->input('brand_id') === '' || $this->input('brand_id') === null)) {
            $this->merge(['brand_id' => null]);
        }

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
        /** @var string|null $id */
        $id = $this->route('id');

        return [
            'name' => [
                'required',
                'string',
                'max:200',
            ],
            'code' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('catalog_items', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'type' => [
                'required',
                Rule::enum(CatalogItemType::class),
            ],
            'category_id' => [
                'required',
                'uuid',
                Rule::exists('categories', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'brand_id' => [
                'nullable',
                'uuid',
                Rule::exists('brands', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'base_unit_id' => [
                'required',
                'uuid',
                Rule::exists('units', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'tax_type' => [
                'required',
                Rule::enum(TaxType::class),
            ],
            'track_stock' => [
                'sometimes',
                'boolean',
            ],
            'is_sellable' => [
                'sometimes',
                'boolean',
            ],
            'is_purchasable' => [
                'sometimes',
                'boolean',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('item_presentations', 'barcode')
                    ->whereNull('deleted_at')
                    ->when($id !== null, fn ($rule) => $rule->where(
                        fn ($q) => $q->where('catalog_item_id', '!=', $id)->orWhere('is_base', false),
                    )),
            ],
            'prices' => [
                'nullable',
                'array',
            ],
            'prices.*.price_type' => [
                'required_with:prices',
                Rule::enum(PriceType::class),
            ],
            'prices.*.price' => [
                'required_with:prices',
                'numeric',
                'min:0',
            ],
            'prices.*.min_quantity' => [
                'nullable',
                'numeric',
                'min:1',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Ya existe un ítem registrado con este código.',
            'code.regex' => 'El código solo puede contener letras, números, puntos, guiones y guiones bajos.',
            'barcode.unique' => 'Este código de barras ya pertenece a otra presentación.',
            'category_id.exists' => 'La categoría seleccionada no existe o no está activa.',
            'brand_id.exists' => 'La marca seleccionada no existe o no está activa.',
            'base_unit_id.exists' => 'La unidad base seleccionada no existe o no está activa.',
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
            'type' => $data['type'],
            'category_id' => $data['category_id'],
            'base_unit_id' => $data['base_unit_id'],
            'tax_type' => $data['tax_type'],
        ];

        if (array_key_exists('code', $data)) {
            $attrs['code'] = $data['code'] !== null && trim((string) $data['code']) !== ''
                ? strtoupper(trim((string) $data['code']))
                : null;
        }

        if (array_key_exists('brand_id', $data)) {
            $attrs['brand_id'] = $data['brand_id'] !== null && trim((string) $data['brand_id']) !== ''
                ? (string) $data['brand_id']
                : null;
        }

        if (array_key_exists('description', $data)) {
            $attrs['description'] = $data['description'] !== null && trim((string) $data['description']) !== ''
                ? trim((string) $data['description'])
                : null;
        }

        if (array_key_exists('track_stock', $data)) {
            $attrs['track_stock'] = (bool) $data['track_stock'];
        }

        if (array_key_exists('is_sellable', $data)) {
            $attrs['is_sellable'] = (bool) $data['is_sellable'];
        }

        if (array_key_exists('is_purchasable', $data)) {
            $attrs['is_purchasable'] = (bool) $data['is_purchasable'];
        }

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        if (array_key_exists('barcode', $data)) {
            $attrs['barcode'] = $data['barcode'] !== null && trim((string) $data['barcode']) !== ''
                ? trim((string) $data['barcode'])
                : null;
        }

        return $attrs;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toPrices(): array
    {
        return (array) ($this->validated()['prices'] ?? []);
    }
}
