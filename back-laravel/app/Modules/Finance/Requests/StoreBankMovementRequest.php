<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Data\BankMovementData;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Models\FinanceConcept;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBankMovementRequest extends FormRequest
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
            'direction' => [
                'required',
                'string',
                Rule::in(['in', 'out']),
            ],
            'concept_id' => [
                'required',
                'string',
                'uuid',
                Rule::exists('finance_concepts', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'payment_method_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('payment_methods', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
            'operation_number' => [
                'nullable',
                'string',
                'max:40',
            ],
            'movement_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'description' => [
                'required',
                'string',
                'max:255',
            ],
            'counterparty_name' => [
                'nullable',
                'string',
                'max:150',
            ],
            'voucher' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->failed()) {
                return;
            }

            $conceptId = (string) $this->input('concept_id');
            $directionStr = (string) $this->input('direction');

            $concept = FinanceConcept::find($conceptId);
            if ($concept !== null) {
                if ($concept->is_system) {
                    $v->errors()->add('concept_id', 'No se pueden registrar movimientos manuales con conceptos del sistema.');
                }

                $direction = MoneyDirection::tryFrom($directionStr);
                if ($direction !== null && ! $concept->direction->allows($direction)) {
                    $v->errors()->add('concept_id', "El concepto seleccionado no permite movimientos de dirección '{$directionStr}'.");
                }
            }
        });
    }

    public function toMovementData(string $bankAccountId, ?string $voucherPath = null, ?string $branchId = null): BankMovementData
    {
        return new BankMovementData(
            bankAccountId: $bankAccountId,
            direction: (string) $this->validated('direction'),
            conceptId: (string) $this->validated('concept_id'),
            amount: (float) $this->validated('amount'),
            movementDate: Carbon::parse((string) $this->validated('movement_date')),
            operationNumber: $this->validated('operation_number') !== null ? trim((string) $this->validated('operation_number')) : null,
            paymentMethodId: $this->validated('payment_method_id') !== null ? (string) $this->validated('payment_method_id') : null,
            description: trim((string) $this->validated('description')),
            counterpartyName: $this->validated('counterparty_name') !== null ? trim((string) $this->validated('counterparty_name')) : null,
            voucherPath: $voucherPath,
            branchId: $branchId,
            isManual: true,
            createdBy: auth()->id() !== null ? (string) auth()->id() : null,
        );
    }
}
