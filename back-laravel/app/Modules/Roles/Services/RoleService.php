<?php

declare(strict_types=1);

namespace App\Modules\Roles\Services;

use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Exceptions\ProtectedRoleException;
use App\Modules\Roles\Exceptions\RoleInUseException;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Role>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return Role::query()
            ->with('permissions')
            ->withCount('users')
            ->search($filters['search'] ?? null)
            ->orderBy('name')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function findById(string $id): ?Role
    {
        return Role::query()
            ->with('permissions')
            ->withCount('users')
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $permissions
     */
    public function create(array $attributes, array $permissions = []): Role
    {
        return DB::transaction(function () use ($attributes, $permissions) {
            /** @var Role $role */
            $role = Role::create($attributes);

            if ($permissions !== []) {
                $permissionModels = Permission::whereIn('slug', $permissions)->get();
                $role->syncPermissions($permissionModels);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            /** @var Role $loadedRole */
            $loadedRole = $role->load('permissions')->loadCount('users');

            return $loadedRole;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $permissions
     */
    public function update(Role $role, array $attributes, ?array $permissions, User $causer): Role
    {
        if ($role->is_protected) {
            throw new ProtectedRoleException($role->name);
        }

        return DB::transaction(function () use ($role, $attributes, $permissions, $causer) {
            $role->update($attributes);

            if ($permissions !== null) {
                /** @var list<string> $oldPermissions */
                $oldPermissions = $role->permissions()->pluck('slug')->sort()->values()->all();
                $permissionModels = Permission::whereIn('slug', $permissions)->get();
                $role->syncPermissions($permissionModels);
                /** @var list<string> $newPermissions */
                $newPermissions = $permissionModels->pluck('slug')->sort()->values()->all();

                if ($oldPermissions !== $newPermissions) {
                    activity()
                        ->performedOn($role)
                        ->causedBy($causer)
                        ->event('updated')
                        ->withProperties([
                            'attributes' => ['permissions' => $newPermissions],
                            'old' => ['permissions' => $oldPermissions],
                        ])
                        ->log('updated');
                }
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $fresh = $role->fresh() ?? $role;

            /** @var Role $loadedRole */
            $loadedRole = $fresh->load('permissions')->loadCount('users');

            return $loadedRole;
        });
    }

    public function delete(Role $role): void
    {
        if ($role->is_protected) {
            throw new ProtectedRoleException($role->name);
        }

        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            throw new RoleInUseException($usersCount);
        }

        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
