<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankOperationRequest extends FormRequest
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
            'type' => [
                'required',
                'string',
                Rule::in(['deposit', 'withdrawal', 'transfer']),
            ],
            'bank_account_id' => [
                'required',
                'string',
                'uuid',
                Rule::exists('bank_accounts', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'cash_session_id' => [
                'required_if:type,deposit,withdrawal',
                'nullable',
                'string',
                'uuid',
                Rule::exists('cash_sessions', 'id'),
            ],
            'target_bank_account_id' => [
                'required_if:type,transfer',
                'nullable',
                'string',
                'uuid',
                'different:bank_account_id',
                Rule::exists('bank_accounts', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
            'exchange_rate' => [
                'nullable',
                'numeric',
                'gt:0',
            ],
            'operation_number' => [
                'required_if:type,deposit',
                'nullable',
                'string',
                'max:40',
            ],
            'operation_date' => [
                'required',
                'date',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
            'voucher' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
        ];
    }
}
