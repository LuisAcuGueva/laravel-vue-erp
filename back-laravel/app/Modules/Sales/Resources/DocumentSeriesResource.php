<?php

declare(strict_types=1);

namespace App\Modules\Sales\Resources;

use App\Modules\Sales\Models\DocumentSeries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentSeries
 */
class DocumentSeriesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $nextNumber = $this->last_number + 1;

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch?->name,
            'box_id' => $this->box_id,
            'box_name' => $this->box?->name,
            'document_type' => $this->document_type->value,
            'document_type_label' => $this->document_type->label(),
            'series' => $this->series,
            'last_number' => $this->last_number,
            'next_number' => $nextNumber,
            'next_full_number' => $this->format($nextNumber),
            'is_electronic' => $this->is_electronic,
            'is_active' => $this->is_active,
            'has_sales' => $this->sales()->exists(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
