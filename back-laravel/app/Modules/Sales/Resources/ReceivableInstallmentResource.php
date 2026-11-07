<?php

declare(strict_types=1);

namespace App\Modules\Sales\Resources;

use App\Modules\Sales\Enums\InstallmentStatus;
use App\Modules\Sales\Models\ReceivableInstallment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReceivableInstallment
 */
class ReceivableInstallmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = Carbon::today();
        $isOverdue = $this->status !== InstallmentStatus::Cancelled
            && (float) $this->balance > 0
            && $this->due_date->lt($today);

        $daysOverdue = $isOverdue ? (int) $this->due_date->diffInDays($today) : 0;

        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'sale_full_number' => $this->sale?->full_number,
            'sale_issue_date' => $this->sale?->issue_date?->toDateString(),
            'sale_status' => $this->sale?->status?->value,
            'client_id' => $this->client_id,
            'client_name' => $this->client?->name,
            'client_document_number' => $this->client?->document_number,
            'client_document_type' => $this->client?->document_type?->value,
            'currency_id' => $this->currency_id,
            'currency_code' => $this->currency?->code,
            'currency_symbol' => $this->currency?->symbol,
            'number' => $this->number,
            'installment_number' => $this->number,
            'due_date' => $this->due_date->toDateString(),
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'balance' => $this->balance,
            'status' => $this->status->value,
            'is_overdue' => $isOverdue,
            'days_overdue' => $daysOverdue,
            'allocations' => CollectionAllocationResource::collection($this->whenLoaded('allocations')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
