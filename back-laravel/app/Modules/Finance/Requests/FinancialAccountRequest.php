<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Support\AccountCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialAccountRequest extends FormRequest
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
        $id = $this->route('id') ?? $this->route('financial_account');
        if ($id instanceof FinancialAccount) {
            $id = $id->id;
        }

        return [
            'code' => [
                'required',
                'string',
                'regex:'.AccountCode::PATTERN,
                Rule::unique('financial_accounts', 'code')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'type' => [
                'required_without:parent_id',
                'nullable',
                Rule::enum(FinancialAccountType::class),
            ],
            'parent_id' => [
                'nullable',
                'uuid',
                'exists:financial_accounts,id',
            ],
            'is_postable' => [
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'El código debe tener entre 2 y 8 dígitos, sin puntos ni espacios.',
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
            'code' => trim((string) $data['code']),
            'name' => trim((string) $data['name']),
            'parent_id' => ! empty($data['parent_id']) ? $data['parent_id'] : null,
        ];

        if (isset($data['type'])) {
            $attrs['type'] = $data['type'];
        }

        if (array_key_exists('is_postable', $data)) {
            $attrs['is_postable'] = (bool) $data['is_postable'];
        }

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        return $attrs;
    }
}
