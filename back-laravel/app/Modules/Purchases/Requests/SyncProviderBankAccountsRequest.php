<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncProviderBankAccountsRequest extends FormRequest
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
            'accounts' => ['present', 'array', 'max:10'],
            'accounts.*.id' => ['nullable', 'uuid'],
            'accounts.*.bank_id' => ['required', 'uuid', Rule::exists('banks', 'id')->whereNull('deleted_at')],
            'accounts.*.currency_id' => ['required', 'uuid', Rule::exists('currencies', 'id')->whereNull('deleted_at')],
            'accounts.*.account_number' => ['required', 'string', 'max:30'],
            'accounts.*.cci' => ['nullable', 'digits:20'],
            'accounts.*.is_detraction_account' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accounts.*.cci.digits' => 'El CCI debe tener 20 dígitos.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toAccounts(): array
    {
        /** @var list<array<string, mixed>> $accounts */
        $accounts = array_values($this->validated()['accounts']);

        return $accounts;
    }
}
