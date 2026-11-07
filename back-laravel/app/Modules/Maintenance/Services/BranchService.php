<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Services;

use App\Modules\Maintenance\Exceptions\BranchRuleException;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Models\SupplierPayment;
use App\Modules\Sales\Models\DocumentSeries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BranchService
{
    /**
     * Filtros y orden del listado, sin paginar. Lo reutilizan `list()` y la exportación CSV.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Branch>
     */
    public function query(array $filters = []): Builder
    {
        $query = Branch::query()
            ->withCount('users')
            ->search(isset($filters['search']) ? (string) $filters['search'] : null);

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('code')->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Branch>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @return Collection<int, Branch>
     */
    public function options(): Collection
    {
        return Branch::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    public function findById(string $id): ?Branch
    {
        return Branch::withCount('users')->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Branch
    {
        return DB::transaction(function () use ($attributes): Branch {
            if (Branch::count() === 0) {
                $attributes['is_main'] = true;
            }

            if (! empty($attributes['is_main'])) {
                Branch::where('is_main', true)->update(['is_main' => false]);
            }

            return Branch::create($attributes);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Branch $branch, array $attributes): Branch
    {
        return DB::transaction(function () use ($branch, $attributes): Branch {
            if ($branch->is_main) {
                if (array_key_exists('is_main', $attributes) && ! $attributes['is_main']) {
                    throw new BranchRuleException('Debe existir una sede principal; marque otra sede como principal.');
                }

                if (array_key_exists('is_active', $attributes) && ! $attributes['is_active']) {
                    throw new BranchRuleException('No se puede desactivar la sede principal.');
                }
            }

            if (! empty($attributes['is_main'])) {
                Branch::where('id', '!=', $branch->id)
                    ->where('is_main', true)
                    ->update(['is_main' => false]);
            }

            $branch->update($attributes);

            return $branch->fresh() ?? $branch;
        });
    }

    public function delete(Branch $branch): bool
    {
        if ($branch->is_main) {
            throw new BranchRuleException('No se puede eliminar la sede principal.');
        }

        if ($branch->users()->exists()) {
            throw new BranchRuleException('La sede tiene usuarios asignados.');
        }

        if ($branch->employees()->exists()) {
            throw new BranchRuleException('La sede tiene empleados asignados.');
        }

        if ($branch->warehouses()->exists()) {
            throw new BranchRuleException('La sede tiene almacenes registrados.');
        }

        if ($branch->boxes()->exists()) {
            throw new BranchRuleException('La sede tiene cajas registradas.');
        }

        if (DocumentSeries::where('branch_id', $branch->id)->exists()) {
            throw new BranchRuleException('La sede tiene series de comprobantes registradas.');
        }

        if (SupplierInvoice::where('branch_id', $branch->id)->exists() || SupplierPayment::where('branch_id', $branch->id)->exists()) {
            throw new BranchRuleException('La sede tiene facturas o pagos de proveedores registrados.');
        }

        return (bool) $branch->delete();
    }
}
