<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Requests;

use App\Modules\Configuration\Services\CompanySettingService;
use App\Modules\Ubigeo\Rules\ValidUbigeo;
use App\Support\Validation\ValidRuc;
use Illuminate\Foundation\Http\FormRequest;

class CompanySettingRequest extends FormRequest
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
            'legal_name' => [
                'required',
                'string',
                'max:200',
            ],
            'trade_name' => [
                'nullable',
                'string',
                'max:200',
            ],
            'legal_representative' => [
                'nullable',
                'string',
                'max:150',
            ],
            'ruc' => [
                'required',
                'string',
                new ValidRuc,
            ],
            'address' => [
                'nullable',
                'string',
                'max:255',
            ],
            'ubigeo' => [
                'nullable',
                'string',
                new ValidUbigeo(app(CompanySettingService::class)->current()->ubigeo),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],
            'email' => [
                'nullable',
                'email',
                'max:150',
            ],
            'website' => [
                'nullable',
                'url',
                'max:200',
            ],
            'default_currency_code' => [
                'prohibited',
            ],
            'default_tax_rate' => [
                'required',
                'numeric',
                'between:0,100',
                'decimal:0,2',
            ],
            'prices_include_tax' => [
                'required',
                'boolean',
            ],
            'terms_conditions' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'warranty_policy' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'social_links' => [
                'nullable',
                'array',
            ],
            'social_links.*' => [
                'nullable',
                'url',
                'max:200',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_currency_code.prohibited' => 'La moneda base se gestiona en Finanzas › Monedas',
        ];
    }

    /**
     * Normaliza los atributos validados listos para persistir.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        $nullableStringFields = [
            'trade_name',
            'legal_representative',
            'address',
            'ubigeo',
            'phone',
            'email',
            'website',
            'terms_conditions',
            'warranty_policy',
        ];

        $attributes = [
            'legal_name' => trim((string) $data['legal_name']),
            'ruc' => trim((string) $data['ruc']),
            'default_tax_rate' => $data['default_tax_rate'],
            'prices_include_tax' => (bool) $data['prices_include_tax'],
            'social_links' => $data['social_links'] ?? null,
        ];

        foreach ($nullableStringFields as $field) {
            if (array_key_exists($field, $data)) {
                $val = $data[$field];
                $attributes[$field] = ($val !== null && trim((string) $val) !== '')
                    ? trim((string) $val)
                    : null;
            }
        }

        return $attributes;
    }
}
