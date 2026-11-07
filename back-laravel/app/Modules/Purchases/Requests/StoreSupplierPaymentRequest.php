<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
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
            'provider_id' => ['nullable', 'uuid', Rule::exists('providers', 'id')],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'currency_id' => ['required', 'uuid', Rule::exists('currencies', 'id')->whereNull('deleted_at')],
            'payment_method_id' => ['required', 'uuid', Rule::exists('payment_methods', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'cash_session_id' => ['nullable', 'uuid'],
            'reference' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
            'allocations' => ['required', 'array', 'min:1', 'max:100'],
            'allocations.*.installment_id' => ['required', 'uuid', 'distinct'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_date.before_or_equal' => 'La fecha de pago no puede ser futura.',
            'allocations.required' => 'Selecciona al menos una cuota a pagar.',
            'allocations.*.installment_id.distinct' => 'Una cuota no puede repetirse en el mismo pago.',
            'allocations.*.amount.gt' => 'El monto a pagar debe ser mayor a 0.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $text = static fn (mixed $v): ?string => isset($v) && trim((string) $v) !== '' ? trim((string) $v) : null;

        return [
            'provider_id' => $data['provider_id'] ?? null,
            'payment_date' => $data['payment_date'],
            'currency_id' => $data['currency_id'],
            'payment_method_id' => $data['payment_method_id'],
            'cash_session_id' => $data['cash_session_id'] ?? null,
            'reference' => $text($data['reference'] ?? null),
            'notes' => $text($data['notes'] ?? null),
            'allocations' => array_map(static fn (array $row): array => [
                'installment_id' => (string) $row['installment_id'],
                'amount' => (string) $row['amount'],
            ], array_values((array) $data['allocations'])),
        ];
    }
}
