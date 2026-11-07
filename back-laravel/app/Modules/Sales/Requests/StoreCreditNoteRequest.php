<?php

declare(strict_types=1);

namespace App\Modules\Sales\Requests;

use App\Modules\Sales\Enums\CreditNoteReason;
use App\Modules\Sales\Enums\CreditNoteSettlementMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sale_id' => ['required', 'uuid', 'exists:sales,id'],
            'document_series_id' => ['required', 'uuid', 'exists:document_series,id'],
            'reason_code' => ['required', 'string', Rule::enum(CreditNoteReason::class)],
            'reason_description' => ['required', 'string', 'min:5', 'max:500'],
            'issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],

            // Motivos 05, 07 y 09 requieren items
            'items' => ['required_if:reason_code,05,07,09', 'array'],
            'items.*.sale_item_id' => ['required_with:items', 'uuid', 'distinct', 'exists:sale_items,id'],
            // El gt:0 de quantity/amount es condicional al motivo (ver withValidator): el motivo 07
            // solo usa quantity y los motivos 05/09 solo usan amount, pero el frontend envía ambos
            // campos en cada ítem (el que no aplica queda en 0).
            'items.*.quantity' => ['required_if:reason_code,07', 'numeric'],
            'items.*.amount' => ['required_if:reason_code,05,09', 'numeric'],
            'items.*.return_to_stock' => ['nullable', 'boolean'],

            // Motivo 04 (Descuento global) requiere amount
            'amount' => ['required_if:reason_code,04', 'numeric', 'gt:0'],

            // Liquidación
            'settlement' => ['required', 'array'],
            'settlement.mode' => ['required', Rule::enum(CreditNoteSettlementMode::class)],
            'settlement.payment_method_id' => ['nullable', 'uuid', 'exists:payment_methods,id'],
            'settlement.cash_session_id' => ['nullable', 'uuid', 'exists:cash_sessions,id'],
            'settlement.reference' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->failed()) {
                return;
            }

            $reason = CreditNoteReason::tryFrom((string) $this->input('reason_code'));

            foreach ((array) $this->input('items', []) as $index => $item) {
                if ($reason === CreditNoteReason::DEVOLUCION_ITEM && (float) ($item['quantity'] ?? 0) <= 0) {
                    $v->errors()->add("items.{$index}.quantity", 'La cantidad debe ser mayor a 0.');
                }

                $usesAmount = $reason === CreditNoteReason::DESCUENTO_ITEM || $reason === CreditNoteReason::DISMINUCION_VALOR;
                if ($usesAmount && (float) ($item['amount'] ?? 0) <= 0) {
                    $v->errors()->add("items.{$index}.amount", 'El monto debe ser mayor a 0.');
                }
            }
        });
    }
}
