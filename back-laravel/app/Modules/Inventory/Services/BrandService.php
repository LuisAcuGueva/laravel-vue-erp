<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\CatalogItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BrandService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Brand>
     */
    public function query(array $filters = []): Builder
    {
        $query = Brand::query();

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('name');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Brand>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @return Collection<int, Brand>
     */
    public function options(): Collection
    {
        return Brand::query()->active()->orderBy('name')->get(['id', 'name']);
    }

    public function findById(string $id): ?Brand
    {
        return Brand::query()->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Brand
    {
        return Brand::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Brand $brand, array $attributes): Brand
    {
        $brand->update($attributes);

        return $brand->fresh() ?? $brand;
    }

    public function delete(Brand $brand): bool
    {
        if (CatalogItem::where('brand_id', $brand->id)->exists()) {
            throw new InventoryRuleException('No se puede eliminar una marca que está asociada a ítems del catálogo.', 'brand');
        }

        return (bool) $brand->delete();
    }
}
