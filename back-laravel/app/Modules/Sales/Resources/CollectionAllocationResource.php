<?php

declare(strict_types=1);

namespace App\Modules\Sales\Resources;

use App\Modules\Sales\Models\CollectionAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CollectionAllocation
 */
class CollectionAllocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection_id' => $this->collection_id,
            'collection_number' => $this->collection?->number,
            'collection_date' => $this->collection?->collection_date?->toDateString(),
            'receivable_installment_id' => $this->receivable_installment_id,
            'installment_number' => $this->installment?->number,
            'installment_due_date' => $this->installment?->due_date?->toDateString(),
            'sale_id' => $this->sale_id,
            'sale_full_number' => $this->sale?->full_number,
            'amount' => $this->amount,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
