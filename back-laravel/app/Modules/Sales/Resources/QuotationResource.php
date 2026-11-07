<?php

declare(strict_types=1);

namespace App\Modules\Sales\Resources;

use App\Modules\Sales\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quotation
 */
class QuotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = now()->startOfDay();
        $validUntilDate = $this->valid_until ? $this->valid_until->copy()->startOfDay() : null;
        $isExpired = $validUntilDate !== null && $validUntilDate->lt($today) && ! $this->status->isFinal();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'branch_id' => $this->branch_id,
            'client_id' => $this->client_id,
            'contact_id' => $this->contact_id,
            'seller_id' => $this->seller_id,
            'issue_date' => $this->issue_date->format('Y-m-d'),
            'valid_until' => $this->valid_until->format('Y-m-d'),
            'is_expired' => $isExpired,
            'currency_id' => $this->currency_id,
            'exchange_rate' => (float) $this->exchange_rate,
            'price_type' => is_string($this->price_type) ? $this->price_type : $this->price_type->value,
            'prices_include_tax' => $this->prices_include_tax,
            'tax_rate' => (float) $this->tax_rate,
            'global_discount_percent' => (float) $this->global_discount_percent,
            'taxable_base' => (float) $this->taxable_base,
            'exempt_base' => (float) $this->exempt_base,
            'unaffected_base' => (float) $this->unaffected_base,
            'discount_total' => (float) $this->discount_total,
            'tax_total' => (float) $this->tax_total,
            'total' => (float) $this->total,
            'total_base_currency' => (float) $this->total_base_currency,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'notes' => $this->notes,
            'terms' => $this->terms,
            'delivery_time' => $this->delivery_time,
            'payment_terms' => $this->payment_terms,
            'discount_override_by' => $this->discount_override_by,
            'created_by' => $this->created_by,
            'sent_at' => $this->sent_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'cancelled_by' => $this->cancelled_by,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancel_reason' => $this->cancel_reason,
            'converted_sale_id' => $this->converted_sale_id,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'document_type' => $this->client->document_type?->value,
                'document_number' => $this->client->document_number,
                'client_type' => $this->client->client_type->value,
                'email' => $this->client->email,
                'phone' => $this->client->phone,
                'address' => $this->client->address,
            ] : null),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'email' => $this->contact->email,
                'phone' => $this->contact->phone,
                'position' => $this->contact->position,
            ] : null),
            'seller' => $this->whenLoaded('seller', fn () => $this->seller ? [
                'id' => $this->seller->id,
                'name' => $this->seller->full_name,
                'full_name' => $this->seller->full_name,
                'document_number' => $this->seller->document_number,
                'position' => $this->seller->position,
            ] : null),
            'currency' => $this->whenLoaded('currency', fn () => $this->currency ? [
                'id' => $this->currency->id,
                'code' => $this->currency->code,
                'name' => $this->currency->name,
                'symbol' => $this->currency->symbol,
                'decimals' => $this->currency->decimals,
                'is_base' => $this->currency->is_base,
            ] : null),
            'discount_override_user' => $this->whenLoaded('discountOverrideBy', fn () => $this->discountOverrideBy ? [
                'id' => $this->discountOverrideBy->id,
                'name' => $this->discountOverrideBy->name,
            ] : null),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->items_count ?? $this->whenLoaded('items', fn () => $this->items->count()),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
