<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Models\FinanceConcept;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinanceConceptRequest extends FormRequest
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
        $id = $this->route('id') ?? $this->route('concept');
        if ($id instanceof FinanceConcept) {
            $id = $id->id;
        }

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('finance_concepts', 'code')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'direction' => [
                'required',
                Rule::enum(ConceptDirection::class),
            ],
            'financial_account_id' => [
                'nullable',
                'uuid',
                'exists:financial_accounts,id',
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
            'direction' => $data['direction'],
            'financial_account_id' => ! empty($data['financial_account_id']) ? $data['financial_account_id'] : null,
        ];

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        return $attrs;
    }
}
