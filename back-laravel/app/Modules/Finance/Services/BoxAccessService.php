<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Box;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;

class BoxAccessService
{
    public function canOperate(User $user, Box $box): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $roleIds = $user->roles->pluck('id');
        if ($roleIds->isEmpty()) {
            return false;
        }

        return $box->roles()
            ->whereIn('roles.id', $roleIds)
            ->exists();
    }

    /**
     * @return Collection<int, Box>
     */
    public function availableBoxes(User $user, Branch $activeBranch): Collection
    {
        $query = Box::query()
            ->where('branch_id', $activeBranch->id)
            ->where('is_active', true)
            ->with(['openSession.opener']);

        if (! $user->isAdmin()) {
            $roleIds = $user->roles->pluck('id');
            $query->whereHas('roles', function ($q) use ($roleIds) {
                $q->whereIn('roles.id', $roleIds);
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * @param  Collection<int, Branch>  $accessibleBranches
     * @return Collection<int, Box>
     */
    public function allAccessibleBoxes(User $user, Collection $accessibleBranches): Collection
    {
        $branchIds = $accessibleBranches->pluck('id')->all();

        return Box::query()
            ->whereIn('branch_id', $branchIds)
            ->where('is_active', true)
            ->with(['branch', 'openSession.opener'])
            ->orderBy('name')
            ->get();
    }
}
