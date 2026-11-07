<?php

declare(strict_types=1);

namespace App\Modules\Permissions\Resources;

use App\Modules\Permissions\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Permission
 */
class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Permission $permission */
        $permission = $this->resource;

        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'slug' => $permission->slug,
            'module' => $permission->module,
            'description' => $permission->description,
            'roles_count' => (int) ($permission->roles_count ?? $permission->roles()->count()),
            'created_at' => $permission->created_at?->toIso8601String(),
            'updated_at' => $permission->updated_at?->toIso8601String(),
        ];
    }
}
