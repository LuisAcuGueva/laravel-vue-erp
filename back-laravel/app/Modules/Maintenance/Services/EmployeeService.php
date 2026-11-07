<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Services;

use App\Modules\Maintenance\Exceptions\EmployeeRuleException;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    /**
     * Filtros y orden del listado, sin paginar. Lo reutilizan `list()` y la exportación CSV.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Employee>
     */
    public function query(array $filters = []): Builder
    {
        $query = Employee::query()
            ->with([
                'area:id,name',
                'branch:id,code,name',
                'user:id,email,status',
            ])
            ->search(isset($filters['search']) ? (string) $filters['search'] : null);

        if (! empty($filters['branch_id'])) {
            $query->forBranch((string) $filters['branch_id']);
        }

        if (! empty($filters['area_id'])) {
            $query->forArea((string) $filters['area_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('has_user', $filters) && $filters['has_user'] !== null && $filters['has_user'] !== '') {
            $query->hasUser(filter_var($filters['has_user'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Employee>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @return Collection<int, Employee>
     */
    public function options(?string $branchId = null, ?string $search = null): Collection
    {
        return Employee::query()
            ->active()
            ->forBranch($branchId)
            ->search($search)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(50)
            ->get(['id', 'first_name', 'last_name', 'document_number', 'position']);
    }

    public function findById(string $id): ?Employee
    {
        return Employee::query()
            ->with([
                'area:id,name',
                'branch:id,code,name',
                'user:id,email,status',
            ])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Employee
    {
        return Employee::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Employee $employee, array $attributes): Employee
    {
        return DB::transaction(function () use ($employee, $attributes): Employee {
            $employee->update($attributes);
            $fresh = $employee->fresh(['user', 'area', 'branch']) ?? $employee;

            if ($fresh->user !== null) {
                $fullName = $fresh->full_name;
                if ($fresh->user->name !== $fullName) {
                    $fresh->user->update(['name' => $fullName]);
                }
            }

            return $fresh;
        });
    }

    public function delete(Employee $employee): bool
    {
        if (Quotation::where('seller_id', $employee->id)->exists()) {
            throw new EmployeeRuleException('No se puede eliminar un empleado con cotizaciones asociadas.');
        }

        if (Sale::where('seller_id', $employee->id)->exists()) {
            throw new EmployeeRuleException('No se puede eliminar un empleado con ventas asociadas.');
        }

        return DB::transaction(function () use ($employee): bool {
            $employee->update(['user_id' => null]);

            return (bool) $employee->delete();
        });
    }
}
