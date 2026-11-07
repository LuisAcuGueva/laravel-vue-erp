<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Modules\Auth\Models\Session;
use App\Modules\Users\Exceptions\LastAdminException;
use App\Modules\Users\Exceptions\SelfDeletionException;
use App\Modules\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class UserService
{
    /**
     * Filtros y orden del listado, sin paginar. Lo reutilizan `list()` y la exportación CSV.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    public function query(array $filters = []): Builder
    {
        return User::query()
            ->with(['roles:id,name', 'branches'])
            ->search($filters['search'] ?? null)
            ->role($filters['role'] ?? null)
            ->status($filters['status'] ?? null)
            ->withoutEmployee(filter_var($filters['without_employee'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    public function findById(string $id): ?User
    {
        return User::query()
            ->with(['roles:id,name', 'branches'])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $roles
     */
    public function create(array $attributes, array $roles): User
    {
        return DB::transaction(function () use ($attributes, $roles) {
            $attributes['status'] ??= User::STATUS_ACTIVE;
            $attributes['token_version'] = 1;

            $user = User::create($attributes);
            $user->syncRoles($roles);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $user->load('roles');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $roles
     */
    public function update(User $user, array $attributes, ?array $roles = null): User
    {
        if ($user->hasRole('admin') && $roles !== null && ! in_array('admin', $roles, true)) {
            $adminCount = User::role('admin')->count();
            if ($adminCount <= 1) {
                throw new LastAdminException('No puede quitar el rol admin al último administrador del sistema.');
            }
        }

        if ($user->hasRole('admin') && isset($attributes['status']) && $attributes['status'] === User::STATUS_INACTIVE) {
            $activeAdminCount = User::role('admin')->where('status', User::STATUS_ACTIVE)->count();
            if ($activeAdminCount <= 1) {
                throw new LastAdminException('No puede desactivar al último administrador del sistema.');
            }
        }

        return DB::transaction(function () use ($user, $attributes, $roles) {
            $currentRoles = $user->roles->pluck('name')->all();
            $rolesChanged = $roles !== null && (
                count(array_diff($roles, $currentRoles)) > 0 ||
                count(array_diff($currentRoles, $roles)) > 0
            );

            $passwordChanged = ! empty($attributes['password']);
            $statusChanged = isset($attributes['status']) && $attributes['status'] !== $user->status;

            if (! $passwordChanged) {
                unset($attributes['password']);
            }

            $user->update($attributes);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            if ($rolesChanged || $passwordChanged || $statusChanged) {
                $user->increment('token_version');
                // Elimina las sesiones del usuario; refresh_tokens.session_id cascadea por FK.
                Session::where('user_id', $user->id)->delete();
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $fresh = $user->fresh() ?? $user;

            return $fresh->load('roles');
        });
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw new SelfDeletionException;
        }

        if ($user->hasRole('admin')) {
            $adminCount = User::role('admin')->count();
            if ($adminCount <= 1) {
                throw new LastAdminException('No puede eliminar al último administrador del sistema.');
            }
        }

        DB::transaction(function () use ($user) {
            // Elimina las sesiones del usuario; refresh_tokens.session_id cascadea por FK.
            Session::where('user_id', $user->id)->delete();
            $user->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}
