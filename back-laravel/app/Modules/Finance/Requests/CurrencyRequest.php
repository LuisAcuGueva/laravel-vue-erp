<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurrencyRequest extends FormRequest
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
        $id = $this->route('id') ?? $this->route('currency');
        if ($id instanceof Currency) {
            $id = $id->id;
        }

        return [
            'code' => [
                'required',
                'string',
                'size:3',
                Rule::unique('currencies', 'code')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:40',
            ],
            'symbol' => [
                'required',
                'string',
                'max:5',
            ],
            'sunat_code' => [
                'nullable',
                'string',
                'max:5',
            ],
            'decimals' => [
                'required',
                'integer',
                'between:0,4',
            ],
            'is_base' => [
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
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        $attrs = [
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
            'symbol' => trim((string) $data['symbol']),
            'decimals' => (int) $data['decimals'],
        ];

        if (array_key_exists('sunat_code', $data)) {
            $attrs['sunat_code'] = $data['sunat_code'] !== null && trim((string) $data['sunat_code']) !== ''
                ? trim((string) $data['sunat_code'])
                : null;
        }

        if (array_key_exists('is_base', $data)) {
            $attrs['is_base'] = (bool) $data['is_base'];
        }

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        return $attrs;
    }
}
