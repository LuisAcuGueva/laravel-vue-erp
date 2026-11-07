<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\FinanceConcept;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinanceConcept
 */
class FinanceConceptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FinanceConcept $concept */
        $concept = $this->resource;

        return [
            'id' => $concept->id,
            'code' => $concept->code,
            'name' => $concept->name,
            'direction' => $concept->direction instanceof \BackedEnum ? $concept->direction->value : $concept->direction,
            'financial_account_id' => $concept->financial_account_id,
            'is_system' => $concept->is_system,
            'is_active' => $concept->is_active,
            'financial_account' => FinancialAccountResource::make($this->whenLoaded('financialAccount')),
            'created_at' => $concept->created_at?->toIso8601String(),
            'updated_at' => $concept->updated_at?->toIso8601String(),
        ];
    }
}
