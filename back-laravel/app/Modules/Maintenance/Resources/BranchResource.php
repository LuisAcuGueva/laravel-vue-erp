<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Resources;

use App\Modules\Maintenance\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Branch
 */
class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Branch $branch */
        $branch = $this->resource;

        return [
            'id' => $branch->id,
            'code' => $branch->code,
            'name' => $branch->name,
            'address' => $branch->address,
            'ubigeo' => $branch->ubigeo,
            'phone' => $branch->phone,
            'email' => $branch->email,
            'is_main' => $branch->is_main,
            'is_active' => $branch->is_active,
            'users_count' => $this->whenCounted('users'),
            'created_at' => $branch->created_at?->toIso8601String(),
            'updated_at' => $branch->updated_at?->toIso8601String(),
        ];
    }
}
