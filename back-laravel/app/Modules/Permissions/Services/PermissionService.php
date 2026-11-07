<?php

declare(strict_types=1);

namespace App\Modules\Permissions\Services;

use App\Modules\Permissions\Exceptions\PermissionInUseException;
use App\Modules\Permissions\Models\Permission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\PermissionRegistrar;

class PermissionService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Permission>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Permission::query()
            ->withCount('roles')
            ->search($filters['search'] ?? null)
            ->module($filters['module'] ?? null)
            ->orderBy('module')
            ->orderBy('slug')
            ->paginate(perPage: (int) ($filters['per_page'] ?? 15), page: (int) ($filters['page'] ?? 1));
    }

    public function findById(string $id): ?Permission
    {
        return Permission::query()->withCount('roles')->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Permission
    {
        if (empty($attributes['module']) && isset($attributes['slug']) && str_contains($attributes['slug'], '.')) {
            $attributes['module'] = explode('.', $attributes['slug'])[0];
        }

        /** @var Permission $permission */
        $permission = Permission::create($attributes);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var Permission $permissionWithCount */
        $permissionWithCount = $permission->loadCount('roles');

        return $permissionWithCount;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Permission $permission, array $attributes): Permission
    {
        if (empty($attributes['module']) && isset($attributes['slug']) && str_contains($attributes['slug'], '.')) {
            $attributes['module'] = explode('.', $attributes['slug'])[0];
        }

        $permission->update($attributes);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $fresh = $permission->fresh() ?? $permission;

        /** @var Permission $freshWithCount */
        $freshWithCount = $fresh->loadCount('roles');

        return $freshWithCount;
    }

    public function delete(Permission $permission): void
    {
        $rolesCount = $permission->roles()->count();
        if ($rolesCount > 0) {
            throw new PermissionInUseException($rolesCount);
        }

        $permission->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
