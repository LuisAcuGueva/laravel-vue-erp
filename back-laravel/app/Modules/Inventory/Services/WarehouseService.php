<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Warehouse>
     */
    public function query(array $filters = []): Builder
    {
        $query = Warehouse::query()->with('branch');

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (string) $filters['branch_id']);
        }

        // Sedes que el usuario puede ver; las resuelve el controller (el servicio no conoce al usuario).
        if (array_key_exists('branch_ids', $filters) && is_array($filters['branch_ids'])) {
            $query->whereIn('branch_id', $filters['branch_ids']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('code');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Warehouse>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  Collection<int, Branch>  $accessibleBranches
     * @return Collection<int, Warehouse>
     */
    public function options(?Branch $branch, Collection $accessibleBranches, string $scope = 'active_branch'): Collection
    {
        $query = Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name');

        if ($scope === 'all') {
            $branchIds = $accessibleBranches->pluck('id')->all();
            $query->whereIn('branch_id', $branchIds);
        } else {
            if ($branch === null) {
                return new Collection;
            }
            $query->where('branch_id', $branch->id);
        }

        return $query->get(['id', 'code', 'name', 'branch_id', 'is_default']);
    }

    public function findById(string $id): ?Warehouse
    {
        return Warehouse::query()->with('branch')->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Warehouse
    {
        return DB::transaction(function () use ($attributes) {
            $branchId = (string) $attributes['branch_id'];
            $isDefault = ! empty($attributes['is_default']);

            if ($isDefault) {
                Warehouse::where('branch_id', $branchId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            } else {
                if (! Warehouse::where('branch_id', $branchId)->exists()) {
                    $attributes['is_default'] = true;
                }
            }

            return Warehouse::create($attributes);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Warehouse $warehouse, array $attributes): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $attributes) {
            $branchId = (string) ($attributes['branch_id'] ?? $warehouse->branch_id);

            if (array_key_exists('is_default', $attributes) && (bool) $attributes['is_default']) {
                Warehouse::where('branch_id', $branchId)
                    ->where('id', '!=', $warehouse->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $warehouse->update($attributes);

            return $warehouse->fresh() ?? $warehouse;
        });
    }

    public function delete(Warehouse $warehouse): bool
    {
        if (Stock::where('warehouse_id', $warehouse->id)->where('quantity', '!=', 0)->exists()) {
            throw new InventoryRuleException('No se puede eliminar un almacén con stock disponible.', 'warehouse');
        }

        if (KardexMovement::where('warehouse_id', $warehouse->id)->exists()) {
            throw new InventoryRuleException('No se puede eliminar un almacén con movimientos de kardex registrados.', 'warehouse');
        }

        return (bool) $warehouse->delete();
    }
}
