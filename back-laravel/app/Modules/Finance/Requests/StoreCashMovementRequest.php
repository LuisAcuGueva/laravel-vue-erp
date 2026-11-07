<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Data\CashMovementData;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCashMovementRequest extends FormRequest
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
                'required',
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
            'reference' => [
                'nullable',
                'string',
                'max:60',
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
            $paymentMethodId = (string) $this->input('payment_method_id');
            $reference = $this->input('reference');

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

            $paymentMethod = PaymentMethod::find($paymentMethodId);
            if ($paymentMethod !== null) {
                if ($paymentMethod->settlement !== Settlement::CashBox) {
                    $v->errors()->add('payment_method_id', 'Los movimientos manuales de caja deben usar un método de pago liquidado en caja.');
                }

                if ($paymentMethod->requires_reference && blank($reference)) {
                    $v->errors()->add('reference', 'La referencia es obligatoria para el método de pago seleccionado.');
                }
            }
        });
    }

    public function toMovementData(string $sessionId): CashMovementData
    {
        return new CashMovementData(
            sessionId: $sessionId,
            direction: (string) $this->validated('direction'),
            conceptId: (string) $this->validated('concept_id'),
            paymentMethodId: (string) $this->validated('payment_method_id'),
            amount: (float) $this->validated('amount'),
            reference: $this->validated('reference') !== null ? trim((string) $this->validated('reference')) : null,
            description: trim((string) $this->validated('description')),
            counterpartyName: $this->validated('counterparty_name') !== null ? trim((string) $this->validated('counterparty_name')) : null,
            isManual: true,
            createdBy: auth()->id() !== null ? (string) auth()->id() : null,
            movedAt: now(),
        );
    }
}
