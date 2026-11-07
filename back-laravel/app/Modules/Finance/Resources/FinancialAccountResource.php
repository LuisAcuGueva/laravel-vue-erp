<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialAccount
 */
class FinancialAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FinancialAccount $account */
        $account = $this->resource;

        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type instanceof \BackedEnum ? $account->type->value : $account->type,
            'parent_id' => $account->parent_id,
            'level' => $account->level,
            'is_postable' => $account->is_postable,
            'is_active' => $account->is_active,
            'parent' => self::make($this->whenLoaded('parent')),
            'children' => self::collection($this->whenLoaded('children')),
            'created_at' => $account->created_at?->toIso8601String(),
            'updated_at' => $account->updated_at?->toIso8601String(),
        ];
    }
}
