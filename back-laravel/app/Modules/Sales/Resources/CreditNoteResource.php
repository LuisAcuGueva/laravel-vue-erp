<?php

declare(strict_types=1);

namespace App\Modules\Sales\Resources;

use App\Modules\Sales\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditNote
 */
class CreditNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'sale_id' => $this->sale_id,
            'sale' => $this->relationLoaded('sale') && $this->sale !== null ? [
                'id' => $this->sale->id,
                'full_number' => $this->sale->full_number,
                'document_type' => $this->sale->document_type instanceof \BackedEnum
                    ? $this->sale->document_type->value
                    : (string) $this->sale->document_type,
                'issue_date' => $this->sale->issue_date instanceof \DateTimeInterface
                    ? $this->sale->issue_date->format('Y-m-d')
                    : (string) $this->sale->issue_date,
                'total' => (string) $this->sale->total,
                'balance' => (string) $this->sale->balance,
            ] : [
                'id' => $this->sale_id,
            ],
            'document_series_id' => $this->document_series_id,
            'series' => $this->series,
            'number' => $this->number,
            'full_number' => $this->full_number,
            'issue_date' => $this->issue_date instanceof \DateTimeInterface
                ? $this->issue_date->format('Y-m-d')
                : (string) $this->issue_date,
            'issued_at' => $this->issued_at?->toISOString(),
            'reason_code' => $this->reason_code->value,
            'reason_name' => $this->reason_code->description(),
            'reason_description' => $this->reason_description,
            'client_id' => $this->client_id,
            'client_name' => $this->client_name,
            'client_document_type' => $this->client_document_type,
            'client_document_number' => $this->client_document_number,
            'client_address' => $this->client_address,
            'client' => [
                'id' => $this->client_id,
                'name' => $this->client_name,
                'document_type' => $this->client_document_type,
                'document_number' => $this->client_document_number,
                'address' => $this->client_address,
            ],
            'currency_id' => $this->currency_id,
            'currency_code' => $this->currency->code ?? 'PEN',
            'currency_symbol' => $this->currency->symbol ?? 'S/',
            'currency_decimals' => $this->currency->decimals ?? 2,
            'exchange_rate' => (string) $this->exchange_rate,
            'tax_rate' => (string) $this->tax_rate,
            'prices_include_tax' => (bool) $this->prices_include_tax,
            'taxable_base' => (string) $this->taxable_base,
            'exempt_base' => (string) $this->exempt_base,
            'unaffected_base' => (string) $this->unaffected_base,
            'tax_total' => (string) $this->tax_total,
            'total' => (string) $this->total,
            'applied_to_balance' => (string) $this->applied_to_balance,
            'refunded_amount' => (string) $this->refunded_amount,
            'settlement_mode' => $this->settlement_mode->value,
            'refund_payment_method_id' => $this->refund_payment_method_id,
            'refund_payment_method_name' => $this->refundPaymentMethod?->name,
            'refund_cash_session_id' => $this->refund_cash_session_id,
            'warehouse_id' => $this->warehouse_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'einvoice_status' => $this->einvoice_status,
            'einvoice_attempts' => $this->einvoice_attempts,
            'einvoice_response' => $this->einvoice_response,
            'einvoice_sent_at' => $this->einvoice_sent_at?->toISOString(),
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'cancelled_by' => $this->cancelled_by,
            'canceller_name' => $this->canceller?->name,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancel_reason' => $this->cancel_reason,
            'items' => CreditNoteItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
