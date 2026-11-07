<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Purchases\Enums\PaymentCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
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
            'provider_id' => ['required', 'uuid', Rule::exists('providers', 'id')->whereNull('deleted_at')],
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouses', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency_id' => ['required', 'uuid', Rule::exists('currencies', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'payment_condition' => ['required', Rule::enum(PaymentCondition::class)],
            'credit_days' => ['required_if:payment_condition,credit', 'nullable', 'integer', 'between:1,365'],
            'prices_include_tax' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.item_presentation_id' => [
                'nullable',
                'uuid',
                Rule::exists('item_presentations', 'id')->whereNull('deleted_at'),
            ],
            'items.*.description' => ['nullable', 'string', 'max:255', 'required_without:items.*.item_presentation_id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_type' => ['nullable', Rule::enum(TaxType::class)],
            'items.*.purchase_request_item_id' => ['nullable', 'uuid', Rule::exists('purchase_request_items', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credit_days.required_if' => 'Indica los días de crédito.',
            'credit_days.between' => 'Los días de crédito deben estar entre 1 y 365.',
            'items.required' => 'Agrega al menos una línea a la orden.',
            'items.*.description.required_without' => 'Indica un artículo del catálogo o una descripción.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor a 0.',
            'items.*.discount_percent.between' => 'El descuento debe estar entre 0 y 100.',
        ];
    }

    /**
     * Los totales enviados por el cliente (si los hubiera) se ignoran: solo se toman los campos validados.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $text = static fn (mixed $v): ?string => isset($v) && trim((string) $v) !== '' ? trim((string) $v) : null;

        $items = [];
        foreach ((array) $data['items'] as $item) {
            $items[] = [
                'item_presentation_id' => $item['item_presentation_id'] ?? null,
                'description' => $text($item['description'] ?? null),
                'quantity' => (string) $item['quantity'],
                'unit_price' => (string) $item['unit_price'],
                'discount_percent' => (string) ($item['discount_percent'] ?? '0'),
                'tax_type' => $item['tax_type'] ?? null,
                'purchase_request_item_id' => $item['purchase_request_item_id'] ?? null,
            ];
        }

        return [
            'provider_id' => $data['provider_id'],
            'warehouse_id' => $data['warehouse_id'],
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'] ?? null,
            'currency_id' => $data['currency_id'],
            'exchange_rate' => isset($data['exchange_rate']) ? (string) $data['exchange_rate'] : null,
            'payment_condition' => $data['payment_condition'],
            'credit_days' => isset($data['credit_days']) ? (int) $data['credit_days'] : null,
            'prices_include_tax' => array_key_exists('prices_include_tax', $data) ? (bool) $data['prices_include_tax'] : null,
            'notes' => $text($data['notes'] ?? null),
            'terms' => $text($data['terms'] ?? null),
            'items' => $items,
        ];
    }
}
