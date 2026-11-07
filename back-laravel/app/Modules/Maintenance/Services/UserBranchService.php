<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Services;

use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\UserBranch;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserBranchService
{
    /**
     * @param  array<int, string>  $branchIds
     */
    public function sync(User $user, array $branchIds, string $defaultBranchId, User $causer): User
    {
        return DB::transaction(function () use ($user, $branchIds, $defaultBranchId, $causer): User {
            $syncData = [];
            foreach ($branchIds as $branchId) {
                $syncData[$branchId] = [
                    'is_default' => $branchId === $defaultBranchId,
                ];
            }

            $user->branches()->sync($syncData);

            activity()
                ->performedOn($user)
                ->causedBy($causer)
                ->event('branches_synced')
                ->withProperties([
                    'branches' => $branchIds,
                    'default' => $defaultBranchId,
                ])
                ->log('branches_synced');

            return $user->load('branches');
        });
    }

    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(User $user): Collection
    {
        if ($user->isAdmin()) {
            /** @var Collection<int, Branch> $allActive */
            $allActive = Branch::query()->where('is_active', true)->orderBy('name')->get();
            $userBranches = $user->branches;
            $userBranchMap = $userBranches->keyBy('id');
            $hasDefault = $userBranches->contains(fn (Branch $b): bool => (bool) ($b->pivot->is_default ?? false));

            foreach ($allActive as $branch) {
                /** @var Branch|null $existingBranch */
                $existingBranch = $userBranchMap->get($branch->id);
                if ($existingBranch !== null) {
                    $branch->setRelation('pivot', $existingBranch->pivot);
                } else {
                    $isDefault = (! $hasDefault && $branch->is_main);
                    $pivot = new UserBranch([
                        'user_id' => $user->id,
                        'branch_id' => $branch->id,
                        'is_default' => $isDefault,
                    ]);
                    $branch->setRelation('pivot', $pivot);
                }
            }

            return $allActive;
        }

        return $user->branches()->where('is_active', true)->orderBy('name')->get();
    }
}
