<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Services;

use App\Modules\Maintenance\Exceptions\EmployeeRuleException;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Users\Models\User;
use App\Modules\Users\Services\UserService;
use Illuminate\Support\Facades\DB;

class EmployeeUserService
{
    public function __construct(
        private readonly UserService $userService,
        private readonly UserBranchService $userBranchService,
    ) {}

    /**
     * @param  array<string, mixed>  $userAttrs
     * @param  list<string>  $roles
     * @param  list<string>|null  $branchIds
     */
    public function createUser(Employee $employee, array $userAttrs, array $roles, ?array $branchIds, User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $userAttrs, $roles, $branchIds, $actor): Employee {
            if ($employee->user_id !== null) {
                throw new EmployeeRuleException('El empleado ya tiene un usuario vinculado.');
            }

            $userData = array_merge($userAttrs, [
                'name' => $employee->full_name,
                'status' => User::STATUS_ACTIVE,
            ]);

            $user = $this->userService->create($userData, $roles);

            $resolvedBranchIds = $branchIds ?? ($employee->branch_id ? [$employee->branch_id] : []);
            if (! empty($resolvedBranchIds)) {
                $defaultBranchId = $employee->branch_id && in_array($employee->branch_id, $resolvedBranchIds, true)
                    ? $employee->branch_id
                    : $resolvedBranchIds[0];

                $this->userBranchService->sync($user, $resolvedBranchIds, $defaultBranchId, $actor);
            }

            $employee->update(['user_id' => $user->id]);

            return $employee->fresh(['user', 'branch', 'area']) ?? $employee;
        });
    }

    public function link(Employee $employee, User $user): Employee
    {
        return DB::transaction(function () use ($employee, $user): Employee {
            /** @var Employee|null $other */
            $other = Employee::where('user_id', $user->id)
                ->whereKeyNot($employee->id)
                ->first();

            if ($other !== null) {
                throw new EmployeeRuleException("El usuario ya está vinculado al empleado {$other->full_name}.");
            }

            if ($employee->user_id !== null && $employee->user_id !== $user->id) {
                throw new EmployeeRuleException('El empleado ya tiene un usuario vinculado; desvincúlelo primero.');
            }

            $employee->update(['user_id' => $user->id]);
            $user->update(['name' => $employee->full_name]);

            return $employee->fresh(['user', 'branch', 'area']) ?? $employee;
        });
    }

    public function unlink(Employee $employee): Employee
    {
        $employee->update(['user_id' => null]);

        return $employee->fresh(['user', 'branch', 'area']) ?? $employee;
    }
}
