<?php

declare(strict_types=1);

namespace App\Modules\Finance\Resources;

use App\Modules\Finance\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bank
 */
class BankResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Bank $bank */
        $bank = $this->resource;

        return [
            'id' => $bank->id,
            'name' => $bank->name,
            'short_name' => $bank->short_name,
            'is_active' => $bank->is_active,
            'accounts_count' => $this->whenCounted('accounts'),
            'created_at' => $bank->created_at?->toIso8601String(),
            'updated_at' => $bank->updated_at?->toIso8601String(),
        ];
    }
}
