<?php

declare(strict_types=1);

namespace App\Modules\Sales\Resources;

use App\Modules\Sales\Models\Sale;
use App\Support\Pricing\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sale
 */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewCosts = $user && $user->can('sales.view_costs');

        $marginTotal = null;
        if ($canViewCosts) {
            $marginTotal = Decimal::sub((string) $this->total, (string) $this->cost_total, 2);
        }

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'cash_session_id' => $this->cash_session_id,
            'document_series_id' => $this->document_series_id,
            'document_type' => $this->document_type->value,
            'document_type_label' => $this->document_type->label(),
            'series' => $this->series,
            'number' => $this->number,
            'full_number' => $this->full_number,
            'issue_date' => $this->issue_date->toDateString(),
            'issued_at' => $this->issued_at->toIso8601String(),
            'due_date' => $this->due_date?->toDateString(),

            // Datos congelados del cliente
            'client_id' => $this->client_id,
            'client_document_type' => $this->client_document_type,
            'client_document_number' => $this->client_document_number,
            'client_name' => $this->client_name,
            'client_address' => $this->client_address,

            'seller_id' => $this->seller_id,
            'seller_name' => $this->seller?->full_name,
            'quotation_id' => $this->quotation_id,

            // Moneda y Precios
            'currency_id' => $this->currency_id,
            'currency_code' => $this->currency?->code,
            'currency_symbol' => $this->currency?->symbol,
            'currency_decimals' => $this->currency?->decimals,
            'exchange_rate' => $this->exchange_rate,
            'price_type' => is_object($this->price_type) ? $this->price_type->value : $this->price_type,
            'prices_include_tax' => $this->prices_include_tax,
            'tax_rate' => $this->tax_rate,
            'global_discount_percent' => $this->global_discount_percent,

            // Bases imponibles y totales
            'taxable_base' => $this->taxable_base,
            'exempt_base' => $this->exempt_base,
            'unaffected_base' => $this->unaffected_base,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'total_amount' => $this->total,
            'total_base_currency' => $this->total_base_currency,

            // Costos y márgenes condicionados
            'cost_total' => $canViewCosts ? $this->cost_total : null,
            'margin_total' => $marginTotal,

            // Condición y pagos
            'payment_condition' => $this->payment_condition->value,
            'payment_condition_label' => $this->payment_condition->label(),
            'paid_amount' => $this->paid_amount,
            'balance' => $this->balance,

            // Estados
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'einvoice_status' => $this->einvoice_status->value,
            'einvoice_status_label' => $this->einvoice_status->label(),
            'einvoice_attempts' => $this->einvoice_attempts,
            'einvoice_response' => $this->einvoice_response,
            'einvoice_sent_at' => $this->einvoice_sent_at?->toIso8601String(),

            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'cancelled_by' => $this->cancelled_by,
            'canceller_name' => $this->canceller?->name,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,

            // Relaciones anidadas
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => SalePaymentResource::collection($this->whenLoaded('payments')),
            'installments' => ReceivableInstallmentResource::collection($this->whenLoaded('installments')),
            'collection_allocations' => CollectionAllocationResource::collection($this->whenLoaded('collectionAllocations')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
