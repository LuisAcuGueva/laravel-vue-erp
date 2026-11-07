<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Inventory\Resources\KardexMovementResource;
use App\Modules\Purchases\Models\PurchaseReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseReceipt
 */
class PurchaseReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'branch_id' => $this->branch_id,
            'purchase_order_id' => $this->purchase_order_id,
            'provider_id' => $this->provider_id,
            'warehouse_id' => $this->warehouse_id,
            'receipt_date' => $this->receipt_date ? (is_string($this->receipt_date) ? $this->receipt_date : $this->receipt_date->toDateString()) : null,
            'provider_document_type' => $this->provider_document_type->value,
            'provider_document_type_label' => $this->provider_document_type->label(),
            'provider_document_number' => $this->provider_document_number,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'total_cost' => $this->total_cost,
            'created_by' => $this->created_by,
            'confirmed_by' => $this->confirmed_by,
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'cancelled_by' => $this->cancelled_by,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'code' => $this->branch->code,
            ] : null),
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'id' => $this->order->id,
                'number' => $this->order->number,
                'status' => $this->order->status->value,
                'status_label' => $this->order->status->label(),
                'order_date' => $this->order->order_date ? (is_string($this->order->order_date) ? $this->order->order_date : $this->order->order_date->toDateString()) : null,
                'total' => $this->order->total,
                'currency' => $this->order->currency ? [
                    'id' => $this->order->currency->id,
                    'code' => $this->order->currency->code,
                    'symbol' => $this->order->currency->symbol,
                    'decimals' => $this->order->currency->decimals,
                ] : null,
            ] : null),
            'provider' => $this->whenLoaded('provider', fn () => $this->provider ? [
                'id' => $this->provider->id,
                'document_type' => $this->provider->document_type?->value,
                'document_number' => $this->provider->document_number,
                'business_name' => $this->provider->business_name,
                'credit_days' => $this->provider->credit_days,
            ] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ] : null),
            'confirmer' => $this->whenLoaded('confirmer', fn () => $this->confirmer ? [
                'id' => $this->confirmer->id,
                'name' => $this->confirmer->name,
            ] : null),
            'canceller' => $this->whenLoaded('canceller', fn () => $this->canceller ? [
                'id' => $this->canceller->id,
                'name' => $this->canceller->name,
            ] : null),
            'items' => PurchaseReceiptItemResource::collection($this->whenLoaded('items')),
            'kardex_movements' => KardexMovementResource::collection($this->whenLoaded('kardexMovements')),
            'supplier_invoices' => $this->whenLoaded('supplierInvoices', fn () => $this->supplierInvoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'document_number' => $invoice->document_number,
                'document_label' => $invoice->document_label,
                'total' => $invoice->total,
            ])),
        ];
    }
}
