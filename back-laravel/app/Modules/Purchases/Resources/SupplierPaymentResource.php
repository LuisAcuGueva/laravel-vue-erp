<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\SupplierPayment;
use App\Modules\Purchases\Models\SupplierPaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierPayment
 */
class SupplierPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SupplierPayment $payment */
        $payment = $this->resource;

        return [
            'id' => $payment->id,
            'number' => $payment->number,
            'branch_id' => $payment->branch_id,
            'provider_id' => $payment->provider_id,
            'counterparty_name' => $payment->counterparty_name,
            'payment_date' => $payment->payment_date->format('Y-m-d'),
            'currency_id' => $payment->currency_id,
            'exchange_rate' => $payment->exchange_rate,
            'payment_method_id' => $payment->payment_method_id,
            'cash_session_id' => $payment->cash_session_id,
            'bank_account_id' => $payment->bank_account_id,
            'amount' => $payment->amount,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'cancelled_at' => $payment->cancelled_at?->toIso8601String(),
            'cancel_reason' => $payment->cancel_reason,
            'allocations_count' => $this->whenCounted('allocations'),
            'provider' => $this->whenLoaded('provider', fn () => $payment->provider !== null ? [
                'id' => $payment->provider->id,
                'document_type' => $payment->provider->document_type->value,
                'document_number' => $payment->provider->document_number,
                'business_name' => $payment->provider->business_name,
            ] : null),
            'currency' => $this->whenLoaded('currency', fn () => ['id' => $payment->currency->id, 'code' => $payment->currency->code, 'symbol' => $payment->currency->symbol]),
            'payment_method' => $this->whenLoaded('paymentMethod', fn () => [
                'id' => $payment->paymentMethod->id,
                'code' => $payment->paymentMethod->code,
                'name' => $payment->paymentMethod->name,
                'settlement' => $payment->paymentMethod->settlement->value,
            ]),
            'cash_session' => $this->whenLoaded('cashSession', fn () => $payment->cashSession !== null ? ['id' => $payment->cashSession->id, 'number' => $payment->cashSession->number] : null),
            'bank_account' => $this->whenLoaded('bankAccount', fn () => $payment->bankAccount !== null ? [
                'id' => $payment->bankAccount->id,
                'alias' => $payment->bankAccount->alias,
                'account_number' => $payment->bankAccount->account_number,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $payment->creator !== null ? ['id' => $payment->creator->id, 'name' => $payment->creator->name] : null),
            'canceller' => $this->whenLoaded('canceller', fn () => $payment->canceller !== null ? ['id' => $payment->canceller->id, 'name' => $payment->canceller->name] : null),
            'allocations' => $this->whenLoaded('allocations', fn () => $payment->allocations->map(fn (SupplierPaymentAllocation $allocation) => [
                'id' => $allocation->id,
                'payable_installment_id' => $allocation->payable_installment_id,
                'supplier_invoice_id' => $allocation->supplier_invoice_id,
                'amount' => $allocation->amount,
                'installment' => $allocation->relationLoaded('installment') ? [
                    'id' => $allocation->installment->id,
                    'number' => $allocation->installment->number,
                    'due_date' => $allocation->installment->due_date->format('Y-m-d'),
                ] : null,
                'invoice' => $allocation->relationLoaded('invoice') ? [
                    'id' => $allocation->invoice->id,
                    'number' => $allocation->invoice->number,
                    'document_label' => $allocation->invoice->document_label,
                ] : null,
            ])->values()),
            'created_at' => $payment->created_at->toIso8601String(),
            'updated_at' => $payment->updated_at->toIso8601String(),
        ];
    }
}
