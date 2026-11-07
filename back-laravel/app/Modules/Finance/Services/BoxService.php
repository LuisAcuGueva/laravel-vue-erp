<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BoxService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Box>
     */
    public function query(array $filters = []): Builder
    {
        $query = Box::query()->with(['branch', 'roles', 'openSession.opener']);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $like = '%'.addcslashes($search, '%_\\').'%';
            $driver = $query->getConnection()->getDriverName();
            $operator = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function (Builder $q) use ($like, $operator) {
                $q->where('name', $operator, $like)
                    ->orWhere('code', $operator, $like)
                    ->orWhere('description', $operator, $like);
            });
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (string) $filters['branch_id']);
        }

        if (array_key_exists('branch_ids', $filters) && is_array($filters['branch_ids'])) {
            $query->whereIn('branch_id', $filters['branch_ids']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('name');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Box>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    public function findById(string $id): ?Box
    {
        return Box::query()->with(['branch', 'roles', 'openSession.opener'])->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Box
    {
        return DB::transaction(function () use ($attributes): Box {
            /** @var list<string>|null $roleIds */
            $roleIds = $attributes['role_ids'] ?? null;
            unset($attributes['role_ids']);

            /** @var Box $box */
            $box = Box::create($attributes);

            if ($roleIds !== null) {
                $box->roles()->sync($roleIds);
            }

            return $box->load(['branch', 'roles', 'openSession.opener']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Box $box, array $attributes): Box
    {
        return DB::transaction(function () use ($box, $attributes): Box {
            if (array_key_exists('is_active', $attributes) && ! (bool) $attributes['is_active']) {
                if ($box->openSession()->exists()) {
                    throw new FinanceRuleException('No se puede desactivar una caja con sesión abierta.', 'is_active');
                }
            }

            /** @var list<string>|null $roleIds */
            $roleIds = $attributes['role_ids'] ?? null;
            unset($attributes['role_ids']);

            $box->update($attributes);

            if ($roleIds !== null) {
                $box->roles()->sync($roleIds);
            }

            return $box->fresh(['branch', 'roles', 'openSession.opener']) ?? $box;
        });
    }

    public function delete(Box $box): bool
    {
        if ($box->openSession()->exists()) {
            throw new FinanceRuleException('No se puede eliminar una caja con sesión abierta.', 'box');
        }

        if ($box->sessions()->exists()) {
            throw new FinanceRuleException('No se puede eliminar una caja con sesiones registradas.', 'box');
        }

        return (bool) $box->delete();
    }

    /**
     * @param  list<string>  $roleIds
     */
    public function syncRoles(Box $box, array $roleIds): Box
    {
        $box->roles()->sync($roleIds);

        return $box->load(['branch', 'roles', 'openSession.opener']);
    }

    public function suggestedOpening(Box $box): float
    {
        /** @var CashSession|null $lastClosed */
        $lastClosed = $box->sessions()
            ->where('status', CashSessionStatus::Closed->value)
            ->latest('closed_at')
            ->first();

        return $lastClosed !== null && $lastClosed->counted_cash !== null
            ? (float) $lastClosed->counted_cash
            : 0.0;
    }
}
