<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Services;

use App\Modules\Maintenance\Models\Area;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AreaService
{
    /**
     * Filtros y orden del listado, sin paginar. Lo reutilizan `list()` y la exportación CSV.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Area>
     */
    public function query(array $filters = []): Builder
    {
        $query = Area::query()
            ->search(isset($filters['search']) ? (string) $filters['search'] : null);

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('name')->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Area>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @return Collection<int, Area>
     */
    public function options(): Collection
    {
        return Area::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function findById(string $id): ?Area
    {
        return Area::find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Area
    {
        return Area::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Area $area, array $attributes): Area
    {
        $area->update($attributes);

        return $area->fresh() ?? $area;
    }

    public function delete(Area $area): bool
    {
        return (bool) $area->delete();
    }
}
