<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use App\Modules\Purchases\Models\Provider;
use App\Modules\Ubigeo\Rules\ValidUbigeo;
use App\Support\Identity\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderRequest extends FormRequest
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
        $providerId = $this->route('id');
        $docTypeInput = (string) $this->input('document_type');
        $docType = DocumentType::tryFrom($docTypeInput);

        $docNumberRules = ['required', 'string'];
        if ($docType !== null) {
            $docNumberRules = array_merge($docNumberRules, $docType->rulesFor());
        }

        $docNumberRules[] = Rule::unique('providers', 'document_number')
            ->where('document_type', $docTypeInput)
            ->whereNull('deleted_at')
            ->ignore($providerId);

        $currentUbigeo = is_string($providerId)
            ? Provider::query()->whereKey($providerId)->value('ubigeo')
            : null;

        return [
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'document_number' => $docNumberRules,
            'business_name' => ['required', 'string', 'max:200'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'string', new ValidUbigeo(is_string($currentUbigeo) ? $currentUbigeo : null)],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'url', 'max:200'],
            'default_currency_id' => [
                'nullable',
                'uuid',
                Rule::exists('currencies', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'credit_days' => ['required', 'integer', 'between:0,365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_number.unique' => 'El número de documento ya está registrado para este tipo de documento.',
            'default_currency_id.exists' => 'La moneda seleccionada no existe o está inactiva.',
            'credit_days.between' => 'Los días de crédito deben estar entre 0 y 365.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $data = $this->validated();
        $text = static fn (mixed $v): ?string => isset($v) && trim((string) $v) !== '' ? trim((string) $v) : null;

        $attributes = [
            'document_type' => $data['document_type'],
            'document_number' => trim((string) $data['document_number']),
            'business_name' => trim((string) $data['business_name']),
            'trade_name' => $text($data['trade_name'] ?? null),
            'address' => $text($data['address'] ?? null),
            'ubigeo' => $text($data['ubigeo'] ?? null),
            'email' => $text($data['email'] ?? null),
            'phone' => $text($data['phone'] ?? null),
            'website' => $text($data['website'] ?? null),
            'default_currency_id' => $data['default_currency_id'] ?? null,
            'credit_days' => (int) $data['credit_days'],
            'notes' => $text($data['notes'] ?? null),
        ];

        if (array_key_exists('is_active', $data)) {
            $attributes['is_active'] = (bool) $data['is_active'];
        }

        return $attributes;
    }
}
