<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sunat_code') && is_string($this->input('sunat_code'))) {
            $code = trim((string) $this->input('sunat_code'));
            $this->merge(['sunat_code' => $code !== '' ? strtoupper($code) : null]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var string|null $id */
        $id = $this->route('id');
        $categoryId = $this->input('unit_category_id');

        if ($categoryId === null && $id !== null) {
            $existingUnit = Unit::query()->find($id);
            $categoryId = $existingUnit?->unit_category_id;
        }

        $isPackaging = is_string($categoryId) && Str::isUuid($categoryId)
            && (bool) UnitCategory::query()->whereKey($categoryId)->value('is_packaging');

        return [
            'unit_category_id' => [
                $id === null ? 'required' : 'sometimes',
                'string',
                'uuid',
                Rule::exists('unit_categories', 'id')
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('units', 'name')
                    ->where(function (Builder $query) use ($categoryId) {
                        return $query->where('unit_category_id', $categoryId)
                            ->whereNull('deleted_at');
                    })
                    ->ignore($id),
            ],
            'symbol' => [
                'required',
                'string',
                'max:10',
            ],
            'is_base' => [
                'sometimes',
                'boolean',
            ],
            'conversion_factor' => [
                $isPackaging ? 'nullable' : 'required_unless:is_base,true',
                'numeric',
                'gt:0',
                'decimal:0,6',
            ],
            'allow_decimals' => [
                'sometimes',
                'boolean',
            ],
            'sunat_code' => [
                'nullable',
                'string',
                'max:5',
                'alpha_num',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
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
            'symbol' => trim((string) $data['symbol']),
        ];

        if (array_key_exists('unit_category_id', $data)) {
            $attrs['unit_category_id'] = (string) $data['unit_category_id'];
        }

        $isBase = array_key_exists('is_base', $data) ? (bool) $data['is_base'] : false;
        if (array_key_exists('is_base', $data)) {
            $attrs['is_base'] = $isBase;
        }

        if ($isBase) {
            $attrs['conversion_factor'] = '1.000000';
        } elseif (isset($data['conversion_factor'])) {
            $attrs['conversion_factor'] = number_format((float) $data['conversion_factor'], 6, '.', '');
        }

        if (array_key_exists('allow_decimals', $data)) {
            $attrs['allow_decimals'] = (bool) $data['allow_decimals'];
        }

        if (array_key_exists('sunat_code', $data)) {
            $attrs['sunat_code'] = $data['sunat_code'] !== null && trim((string) $data['sunat_code']) !== ''
                ? strtoupper(trim((string) $data['sunat_code']))
                : null;
        }

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        return $attrs;
    }
}
