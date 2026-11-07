<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentMethodRequest extends FormRequest
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
        $id = $this->route('id') ?? $this->route('payment_method');
        if ($id instanceof PaymentMethod) {
            $id = $id->id;
        }

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('payment_methods', 'code')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:60',
            ],
            'kind' => [
                'required',
                Rule::enum(PaymentMethodKind::class),
            ],
            'settlement' => [
                'required',
                Rule::enum(Settlement::class),
            ],
            'bank_account_id' => [
                'required_if:settlement,bank_account',
                'nullable',
                'uuid',
                'exists:bank_accounts,id',
            ],
            'requires_reference' => [
                'sometimes',
                'boolean',
            ],
            'sunat_code' => [
                'nullable',
                'string',
                'max:5',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
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
            'kind' => $data['kind'],
            'settlement' => $data['settlement'],
        ];

        $settlementValue = $data['settlement'] instanceof Settlement ? $data['settlement']->value : (string) $data['settlement'];
        if ($settlementValue === Settlement::BankAccount->value) {
            $attrs['bank_account_id'] = $data['bank_account_id'];
        } else {
            $attrs['bank_account_id'] = null;
        }

        $attrs['sunat_code'] = ! empty($data['sunat_code']) ? trim((string) $data['sunat_code']) : null;
        $attrs['requires_reference'] = (bool) ($data['requires_reference'] ?? false);
        $attrs['sort_order'] = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        return $attrs;
    }
}
