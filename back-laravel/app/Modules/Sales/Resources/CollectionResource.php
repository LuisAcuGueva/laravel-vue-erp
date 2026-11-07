<?php

declare(strict_types=1);

namespace App\Modules\Sales\Resources;

use App\Modules\Sales\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Collection
 */
class CollectionResource extends JsonResource
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
            'branch_name' => $this->branch?->name,
            'client_id' => $this->client_id,
            'client_name' => $this->client?->name,
            'client_document_number' => $this->client?->document_number,
            'client_document_type' => $this->client?->document_type?->value,
            'currency_id' => $this->currency_id,
            'currency_code' => $this->currency?->code,
            'currency_symbol' => $this->currency?->symbol,
            'payment_method_id' => $this->payment_method_id,
            'payment_method_name' => $this->paymentMethod?->name,
            'cash_session_id' => $this->cash_session_id,
            'box_name' => $this->cashSession?->box?->name,
            'bank_account_id' => $this->bank_account_id,
            'payment_date' => $this->payment_date->toDateString(),
            'collection_date' => $this->payment_date->toDateString(),
            'amount' => $this->amount,
            'total_amount' => $this->amount,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'allocations' => CollectionAllocationResource::collection($this->whenLoaded('allocations')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
