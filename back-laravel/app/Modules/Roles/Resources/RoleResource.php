<?php

declare(strict_types=1);

namespace App\Modules\Roles\Resources;

use App\Modules\Roles\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $role->relationLoaded('permissions')
                ? $role->permissions->pluck('slug')->sort()->values()->all()
                : $role->permissions()->pluck('slug')->sort()->values()->all(),
            'users_count' => (int) ($role->users_count ?? $role->users()->count()),
            'is_protected' => (bool) $role->is_protected,
            'created_at' => $role->created_at?->toIso8601String(),
            'updated_at' => $role->updated_at?->toIso8601String(),
        ];
    }
}
