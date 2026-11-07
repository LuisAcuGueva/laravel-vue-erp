<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseOrder $po */
        $po = $this->resource;

        return [
            'id' => $po->id,
            'number' => $po->number,
            'branch_id' => $po->branch_id,
            'provider_id' => $po->provider_id,
            'warehouse_id' => $po->warehouse_id,
            'order_date' => $po->order_date->format('Y-m-d'),
            'expected_date' => $po->expected_date?->format('Y-m-d'),
            'currency_id' => $po->currency_id,
            'exchange_rate' => $po->exchange_rate,
            'payment_condition' => $po->payment_condition->value,
            'payment_condition_label' => $po->payment_condition->label(),
            'credit_days' => $po->credit_days,
            'prices_include_tax' => $po->prices_include_tax,
            'tax_rate' => $po->tax_rate,
            'taxable_base' => $po->taxable_base,
            'exempt_base' => $po->exempt_base,
            'unaffected_base' => $po->unaffected_base,
            'discount_total' => $po->discount_total,
            'tax_total' => $po->tax_total,
            'total' => $po->total,
            'total_base_currency' => $po->total_base_currency,
            'status' => $po->status->value,
            'status_label' => $po->status->label(),
            'notes' => $po->notes,
            'terms' => $po->terms,
            'created_by' => $po->created_by,
            'approved_by' => $po->approved_by,
            'approved_at' => $po->approved_at?->toIso8601String(),
            'rejected_at' => $po->rejected_at?->toIso8601String(),
            'rejection_reason' => $po->rejection_reason,
            'closed_at' => $po->closed_at?->toIso8601String(),
            'cancelled_at' => $po->cancelled_at?->toIso8601String(),
            'cancel_reason' => $po->cancel_reason,
            'items_count' => $this->whenCounted('items'),
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $po->branch->id, 'code' => $po->branch->code, 'name' => $po->branch->name]),
            'provider' => $this->whenLoaded('provider', fn () => [
                'id' => $po->provider->id,
                'document_type' => $po->provider->document_type->value,
                'document_number' => $po->provider->document_number,
                'business_name' => $po->provider->business_name,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => ['id' => $po->warehouse->id, 'code' => $po->warehouse->code, 'name' => $po->warehouse->name]),
            'currency' => $this->whenLoaded('currency', fn () => ['id' => $po->currency->id, 'code' => $po->currency->code, 'symbol' => $po->currency->symbol, 'decimals' => $po->currency->decimals]),
            'creator' => $this->whenLoaded('creator', fn () => $po->creator ? ['id' => $po->creator->id, 'name' => $po->creator->name] : null),
            'approver' => $this->whenLoaded('approver', fn () => $po->approver ? ['id' => $po->approver->id, 'name' => $po->approver->name] : null),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'receipts' => PurchaseReceiptResource::collection($this->whenLoaded('receipts')),
            'created_at' => $po->created_at->toIso8601String(),
            'updated_at' => $po->updated_at->toIso8601String(),
        ];
    }
}
