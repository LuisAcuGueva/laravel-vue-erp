<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Models\BankAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankAccountRequest extends FormRequest
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
        $id = $this->route('id') ?? $this->route('bank_account');
        if ($id instanceof BankAccount) {
            $id = $id->id;
        }

        $bankId = $this->input('bank_id');

        return [
            'bank_id' => [
                'required',
                'uuid',
                'exists:banks,id',
            ],
            'currency_id' => [
                'required',
                'uuid',
                'exists:currencies,id',
            ],
            'branch_id' => [
                'nullable',
                'uuid',
                'exists:branches,id',
            ],
            'alias' => [
                'required',
                'string',
                'max:60',
            ],
            'account_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('bank_accounts', 'account_number')
                    ->where('bank_id', $bankId)
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'cci' => [
                'nullable',
                'string',
                'digits:20',
            ],
            'account_type' => [
                'required',
                Rule::enum(BankAccountType::class),
            ],
            'holder_name' => [
                'nullable',
                'string',
                'max:150',
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
            'bank_id' => $data['bank_id'],
            'currency_id' => $data['currency_id'],
            'alias' => trim((string) $data['alias']),
            'account_number' => trim((string) $data['account_number']),
            'account_type' => $data['account_type'],
        ];

        $attrs['branch_id'] = ! empty($data['branch_id']) ? $data['branch_id'] : null;
        $attrs['cci'] = ! empty($data['cci']) ? trim((string) $data['cci']) : null;
        $attrs['holder_name'] = ! empty($data['holder_name']) ? trim((string) $data['holder_name']) : null;

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        return $attrs;
    }
}
