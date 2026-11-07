<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Purchases\Enums\SupplierDocumentType;
use App\Modules\Purchases\Enums\SupplierInvoiceKind;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreSupplierInvoiceRequest extends FormRequest
{
    /**
     * Pagar en el acto exige además el permiso de registrar pagos.
     */
    public function authorize(): bool
    {
        if (! $this->boolean('pay_now')) {
            return true;
        }

        $user = $this->user();

        return $user instanceof User && $user->can('supplier_payments.create');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(SupplierInvoiceKind::class)],
            'provider_id' => ['required_if:kind,purchase', 'nullable', 'uuid', Rule::exists('providers', 'id')->whereNull('deleted_at')],
            'counterparty_name' => ['required_without:provider_id', 'nullable', 'string', 'max:150'],
            'document_type' => ['required', Rule::enum(SupplierDocumentType::class)],
            'series' => ['nullable', 'string', 'max:10'],
            'document_number' => ['required', 'string', 'max:20'],
            'issue_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency_id' => ['required', 'uuid', Rule::exists('currencies', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'prices_include_tax' => ['sometimes', 'boolean'],
            'purchase_order_id' => ['nullable', 'uuid'],
            'receipt_ids' => ['nullable', 'array', 'max:50'],
            'receipt_ids.*' => ['uuid', 'distinct'],
            'concept_id' => ['required_if:kind,expense', 'nullable', 'uuid', Rule::exists('finance_concepts', 'id')->whereNull('deleted_at')],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.item_presentation_id' => ['nullable', 'uuid', Rule::exists('item_presentations', 'id')->whereNull('deleted_at')],
            'items.*.purchase_receipt_item_id' => ['nullable', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_type' => ['nullable', Rule::enum(TaxType::class)],
            'installments' => ['nullable', 'array', 'max:60'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,xml', 'max:5120'],
            'pay_now' => ['sometimes', 'boolean'],
            'down_payment_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'payment' => ['required_if:pay_now,true,1', 'nullable', 'array'],
            'payment.payment_method_id' => ['required_with:payment', 'uuid', Rule::exists('payment_methods', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'payment.cash_session_id' => ['nullable', 'uuid'],
            'payment.payment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'payment.reference' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider_id.required_if' => 'Selecciona el proveedor de la factura.',
            'counterparty_name.required_without' => 'Indica el proveedor o el nombre del beneficiario.',
            'concept_id.required_if' => 'Selecciona el concepto de egreso del gasto.',
            'issue_date.before_or_equal' => 'La fecha de emisión no puede ser futura.',
            'due_date.after_or_equal' => 'El vencimiento no puede ser anterior a la emisión.',
            'items.required' => 'Agrega al menos una línea a la factura.',
            'items.*.description.required' => 'Indica la descripción de la línea.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor a 0.',
            'items.*.discount_percent.between' => 'El descuento debe estar entre 0 y 100.',
            'payment.required_if' => 'Indica los datos del pago.',
            'payment.payment_method_id.required_with' => 'Selecciona el método de pago.',
            'attachment.max' => 'El adjunto no puede superar los 5 MB.',
        ];
    }

    /**
     * Los totales y saldos enviados por el cliente se ignoran: se calculan en el backend.
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
                'description' => trim((string) $item['description']),
                'item_presentation_id' => $item['item_presentation_id'] ?? null,
                'purchase_receipt_item_id' => $item['purchase_receipt_item_id'] ?? null,
                'quantity' => (string) $item['quantity'],
                'unit_price' => (string) $item['unit_price'],
                'discount_percent' => (string) ($item['discount_percent'] ?? '0'),
                'tax_type' => $item['tax_type'] ?? null,
            ];
        }

        $installments = null;
        if (! empty($data['installments'])) {
            $installments = array_map(static fn (array $row): array => [
                'due_date' => (string) $row['due_date'],
                'amount' => (string) $row['amount'],
            ], array_values((array) $data['installments']));
        }

        $payNow = $this->boolean('pay_now');
        $payment = null;
        if ($payNow) {
            /** @var array<string, mixed> $raw */
            $raw = (array) ($data['payment'] ?? []);
            $payment = [
                'payment_method_id' => (string) $raw['payment_method_id'],
                'cash_session_id' => $raw['cash_session_id'] ?? null,
                'payment_date' => $raw['payment_date'] ?? null,
                'reference' => $text($raw['reference'] ?? null),
            ];
        }

        return [
            'kind' => $data['kind'],
            'provider_id' => $data['provider_id'] ?? null,
            'counterparty_name' => $text($data['counterparty_name'] ?? null),
            'document_type' => $data['document_type'],
            'series' => $text(isset($data['series']) ? mb_strtoupper((string) $data['series']) : null),
            'document_number' => trim((string) $data['document_number']),
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'] ?? null,
            'currency_id' => $data['currency_id'],
            'exchange_rate' => isset($data['exchange_rate']) ? (string) $data['exchange_rate'] : null,
            'prices_include_tax' => array_key_exists('prices_include_tax', $data) ? $this->boolean('prices_include_tax') : null,
            'purchase_order_id' => $data['purchase_order_id'] ?? null,
            'receipt_ids' => array_values((array) ($data['receipt_ids'] ?? [])),
            'concept_id' => $data['concept_id'] ?? null,
            'notes' => $text($data['notes'] ?? null),
            'items' => $items,
            'installments' => $installments,
            'pay_now' => $payNow,
            'down_payment_amount' => $payNow && isset($data['down_payment_amount']) ? (string) $data['down_payment_amount'] : null,
            'payment' => $payment,
        ];
    }

    public function attachment(): ?UploadedFile
    {
        $file = $this->file('attachment');

        return $file instanceof UploadedFile ? $file : null;
    }
}
