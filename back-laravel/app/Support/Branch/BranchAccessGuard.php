<?php

declare(strict_types=1);

namespace App\Support\Branch;

use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BranchAccessGuard
{
    /**
     * @throws ModelNotFoundException<Branch>
     */
    public function assertAccessible(User $user, ?string $branchId): void
    {
        if ($branchId === null) {
            /** @var ModelNotFoundException<Branch> $exception */
            $exception = (new ModelNotFoundException)->setModel(Branch::class, ['']);
            throw $exception;
        }

        $accessibleIds = $this->accessibleBranchIds($user);

        if (! in_array($branchId, $accessibleIds, true)) {
            /** @var ModelNotFoundException<Branch> $exception */
            $exception = (new ModelNotFoundException)->setModel(Branch::class, [$branchId]);
            throw $exception;
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeQuery(Builder $query, User $user, string $column = 'branch_id'): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $accessibleIds = $this->accessibleBranchIds($user);

        return $query->whereIn($column, $accessibleIds);
    }

    /**
     * @return list<string>
     */
    public function accessibleBranchIds(User $user): array
    {
        if ($user->isAdmin()) {
            /** @var list<string> $ids */
            $ids = Branch::pluck('id')->values()->all();

            return $ids;
        }

        /** @var list<string> $ids */
        $ids = $user->branches()
            ->where('is_active', true)
            ->pluck('branches.id')
            ->values()
            ->all();

        return $ids;
    }
}
