<?php

declare(strict_types=1);

namespace App\Modules\Clients\Requests;

use App\Modules\Clients\Enums\ClientType;
use App\Modules\Clients\Models\Client;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Ubigeo\Rules\ValidUbigeo;
use App\Support\Identity\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClientRequest extends FormRequest
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
        $clientId = $this->route('id');
        $docTypeInput = (string) $this->input('document_type');
        $docType = DocumentType::tryFrom($docTypeInput);

        $docNumberRules = ['required', 'string'];
        if ($docType !== null) {
            $docNumberRules = array_merge($docNumberRules, $docType->rulesFor());
        }

        $docNumberRules[] = Rule::unique('clients', 'document_number')
            ->where('document_type', $docTypeInput)
            ->whereNull('deleted_at')
            ->ignore($clientId);

        $currentUbigeo = is_string($clientId)
            ? Client::query()->whereKey($clientId)->value('ubigeo')
            : null;

        return [
            'client_type' => ['required', Rule::enum(ClientType::class)],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'document_number' => $docNumberRules,
            'name' => ['required', 'string', 'max:200'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'representative_name' => ['nullable', 'string', 'max:150'],
            'representative_document' => ['nullable', 'string', 'max:15'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'string', new ValidUbigeo(is_string($currentUbigeo) ? $currentUbigeo : null)],
            'default_price_type' => ['required', Rule::enum(PriceType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $clientType = $this->input('client_type');
            $docType = $this->input('document_type');

            if ($clientType === ClientType::Company->value && $docType !== DocumentType::Ruc->value) {
                $validator->errors()->add('document_type', 'Una empresa debe tener tipo de documento RUC.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_number.unique' => 'El número de documento ya está registrado para este tipo de documento.',
            'ubigeo' => 'El ubigeo seleccionado no es válido.',
        ];
    }

    /**
     * Normaliza los atributos validados listos para persistir.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $data = $this->validated();
        $text = static fn (mixed $v): ?string => isset($v) && trim((string) $v) !== '' ? trim((string) $v) : null;

        $clientType = $data['client_type'] instanceof ClientType
            ? $data['client_type']->value
            : (string) $data['client_type'];

        $name = trim((string) $data['name']);
        if ($clientType === ClientType::Company->value) {
            $name = mb_strtoupper($name);
        }

        $defaultPriceType = $data['default_price_type'] instanceof PriceType
            ? $data['default_price_type']->value
            : (string) $data['default_price_type'];

        $docType = $data['document_type'] instanceof DocumentType
            ? $data['document_type']->value
            : (string) $data['document_type'];

        $attributes = [
            'client_type' => $clientType,
            'document_type' => $docType,
            'document_number' => trim((string) $data['document_number']),
            'name' => $name,
            'trade_name' => $text($data['trade_name'] ?? null),
            'representative_name' => $text($data['representative_name'] ?? null),
            'representative_document' => $text($data['representative_document'] ?? null),
            'email' => $text($data['email'] ?? null),
            'phone' => $text($data['phone'] ?? null),
            'address' => $text($data['address'] ?? null),
            'ubigeo' => $text($data['ubigeo'] ?? null),
            'default_price_type' => $defaultPriceType,
            'description' => $text($data['description'] ?? null),
        ];

        if (array_key_exists('is_active', $data)) {
            $attributes['is_active'] = (bool) $data['is_active'];
        }

        return $attributes;
    }
}
