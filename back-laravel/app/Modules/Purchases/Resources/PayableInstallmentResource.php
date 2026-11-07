<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Resources;

use App\Modules\Purchases\Models\PayableInstallment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayableInstallment
 */
class PayableInstallmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PayableInstallment $installment */
        $installment = $this->resource;

        return [
            'id' => $installment->id,
            'supplier_invoice_id' => $installment->supplier_invoice_id,
            'provider_id' => $installment->provider_id,
            'currency_id' => $installment->currency_id,
            'number' => $installment->number,
            'due_date' => $installment->due_date->format('Y-m-d'),
            'amount' => $installment->amount,
            'paid_amount' => $installment->paid_amount,
            'balance' => $installment->balance,
            'status' => $installment->status->value,
            'status_label' => $installment->status->label(),
            'is_overdue' => $installment->is_overdue,
            'days_overdue' => $installment->is_overdue ? (int) $installment->due_date->diffInDays(today()) : 0,
            'invoice' => $this->whenLoaded('invoice', fn () => [
                'id' => $installment->invoice->id,
                'number' => $installment->invoice->number,
                'kind' => $installment->invoice->kind->value,
                'document_type' => $installment->invoice->document_type->value,
                'document_type_label' => $installment->invoice->document_type->label(),
                'series' => $installment->invoice->series,
                'document_number' => $installment->invoice->document_number,
                'document_label' => $installment->invoice->document_label,
                'issue_date' => $installment->invoice->issue_date->format('Y-m-d'),
                'counterparty_name' => $installment->invoice->counterparty_name,
            ]),
            'provider' => $this->whenLoaded('provider', fn () => $installment->provider !== null ? [
                'id' => $installment->provider->id,
                'document_type' => $installment->provider->document_type->value,
                'document_number' => $installment->provider->document_number,
                'business_name' => $installment->provider->business_name,
            ] : null),
            'currency' => $this->whenLoaded('currency', fn () => [
                'id' => $installment->currency->id,
                'code' => $installment->currency->code,
                'symbol' => $installment->currency->symbol,
            ]),
        ];
    }
}
