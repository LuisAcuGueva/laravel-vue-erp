<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Models\SupplierInvoiceItem;
use App\Modules\Purchases\Models\SupplierPaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierInvoice
 */
class SupplierInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SupplierInvoice $invoice */
        $invoice = $this->resource;

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'branch_id' => $invoice->branch_id,
            'kind' => $invoice->kind->value,
            'kind_label' => $invoice->kind->label(),
            'provider_id' => $invoice->provider_id,
            'counterparty_name' => $invoice->counterparty_name,
            'document_type' => $invoice->document_type->value,
            'document_type_label' => $invoice->document_type->label(),
            'series' => $invoice->series,
            'document_number' => $invoice->document_number,
            'document_label' => $invoice->document_label,
            'issue_date' => $invoice->issue_date->format('Y-m-d'),
            'due_date' => $invoice->due_date->format('Y-m-d'),
            'is_overdue' => $invoice->status->value !== 'cancelled'
                && (float) $invoice->balance > 0
                && $invoice->due_date->lt(today()),
            'currency_id' => $invoice->currency_id,
            'exchange_rate' => $invoice->exchange_rate,
            'prices_include_tax' => $invoice->prices_include_tax,
            'tax_rate' => $invoice->tax_rate,
            'taxable_base' => $invoice->taxable_base,
            'exempt_base' => $invoice->exempt_base,
            'unaffected_base' => $invoice->unaffected_base,
            'discount_total' => $invoice->discount_total,
            'tax_total' => $invoice->tax_total,
            'total' => $invoice->total,
            'paid_amount' => $invoice->paid_amount,
            'balance' => $invoice->balance,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'purchase_order_id' => $invoice->purchase_order_id,
            'concept_id' => $invoice->concept_id,
            'notes' => $invoice->notes,
            'attachment_url' => $invoice->attachment_path !== null
                ? url("/api/v1/purchases/supplier-invoices/{$invoice->id}/attachment")
                : null,
            'cancelled_at' => $invoice->cancelled_at?->toIso8601String(),
            'cancel_reason' => $invoice->cancel_reason,
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $invoice->branch->id, 'code' => $invoice->branch->code, 'name' => $invoice->branch->name]),
            'provider' => $this->whenLoaded('provider', fn () => $invoice->provider !== null ? [
                'id' => $invoice->provider->id,
                'document_type' => $invoice->provider->document_type->value,
                'document_number' => $invoice->provider->document_number,
                'business_name' => $invoice->provider->business_name,
            ] : null),
            'currency' => $this->whenLoaded('currency', fn () => ['id' => $invoice->currency->id, 'code' => $invoice->currency->code, 'symbol' => $invoice->currency->symbol, 'decimals' => $invoice->currency->decimals]),
            'order' => $this->whenLoaded('order', fn () => $invoice->order !== null ? ['id' => $invoice->order->id, 'number' => $invoice->order->number] : null),
            'concept' => $this->whenLoaded('concept', fn () => $invoice->concept !== null ? ['id' => $invoice->concept->id, 'code' => $invoice->concept->code, 'name' => $invoice->concept->name] : null),
            'creator' => $this->whenLoaded('creator', fn () => $invoice->creator !== null ? ['id' => $invoice->creator->id, 'name' => $invoice->creator->name] : null),
            'canceller' => $this->whenLoaded('canceller', fn () => $invoice->canceller !== null ? ['id' => $invoice->canceller->id, 'name' => $invoice->canceller->name] : null),
            'items' => $this->whenLoaded('items', fn () => $invoice->items->map(fn (SupplierInvoiceItem $item) => [
                'id' => $item->id,
                'line' => $item->line,
                'description' => $item->description,
                'catalog_item_id' => $item->catalog_item_id,
                'item_presentation_id' => $item->item_presentation_id,
                'purchase_receipt_item_id' => $item->purchase_receipt_item_id,
                'catalog_item' => $item->relationLoaded('catalogItem') && $item->catalogItem !== null
                    ? ['id' => $item->catalogItem->id, 'code' => $item->catalogItem->code, 'name' => $item->catalogItem->name]
                    : null,
                'presentation' => $item->relationLoaded('presentation') && $item->presentation !== null
                    ? ['id' => $item->presentation->id, 'name' => $item->presentation->name]
                    : null,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'tax_type' => $item->tax_type->value,
                'line_subtotal' => $item->line_subtotal,
                'line_tax' => $item->line_tax,
                'line_total' => $item->line_total,
            ])->values()),
            'installments' => PayableInstallmentResource::collection($this->whenLoaded('installments')),
            'receipts' => $this->whenLoaded('receipts', fn () => $invoice->receipts->map(fn ($receipt) => [
                'id' => $receipt->id,
                'number' => $receipt->number,
                'receipt_date' => $receipt->receipt_date->format('Y-m-d'),
                'status' => $receipt->status->value,
                'total_cost' => $receipt->total_cost,
            ])->values()),
            'payments' => $this->whenLoaded('allocations', fn () => $invoice->allocations->map(fn (SupplierPaymentAllocation $allocation) => [
                'allocation_id' => $allocation->id,
                'payable_installment_id' => $allocation->payable_installment_id,
                'amount' => $allocation->amount,
                'payment' => $allocation->relationLoaded('payment') ? [
                    'id' => $allocation->payment->id,
                    'number' => $allocation->payment->number,
                    'payment_date' => $allocation->payment->payment_date->format('Y-m-d'),
                    'amount' => $allocation->payment->amount,
                    'status' => $allocation->payment->status->value,
                    'status_label' => $allocation->payment->status->label(),
                ] : null,
            ])->values()),
            'created_at' => $invoice->created_at->toIso8601String(),
            'updated_at' => $invoice->updated_at->toIso8601String(),
        ];
    }
}
