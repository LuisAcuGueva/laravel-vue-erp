<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UnitService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<UnitCategory>
     */
    public function queryCategories(array $filters = []): Builder
    {
        $query = UnitCategory::query()->with(['units', 'baseUnit'])->withCount('units');

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
     * @return LengthAwarePaginator<int, UnitCategory>
     */
    public function listCategories(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->queryCategories($filters)->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @return Collection<int, UnitCategory>
     */
    public function optionsCategories(): Collection
    {
        return UnitCategory::query()->active()->orderBy('name')->get(['id', 'name', 'is_packaging']);
    }

    public function findCategoryById(string $id): ?UnitCategory
    {
        return UnitCategory::query()->with(['units', 'baseUnit'])->withCount('units')->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCategory(array $attributes): UnitCategory
    {
        return UnitCategory::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCategory(UnitCategory $category, array $attributes): UnitCategory
    {
        $changesPackaging = array_key_exists('is_packaging', $attributes)
            && (bool) $attributes['is_packaging'] !== $category->is_packaging;

        if ($changesPackaging && $category->units()->exists()) {
            throw new InventoryRuleException('No se puede cambiar el tipo de empaque de una categoría que tiene unidades registradas.', 'is_packaging');
        }

        $category->update($attributes);

        return $category->fresh() ?? $category;
    }

    public function deleteCategory(UnitCategory $category): bool
    {
        if ($category->units()->exists()) {
            throw new InventoryRuleException('No se puede eliminar una categoría que tiene unidades registradas.', 'unit_category');
        }

        return (bool) $category->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Unit>
     */
    public function query(array $filters = []): Builder
    {
        $query = Unit::query()->with('category');

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        if (! empty($filters['unit_category_id'])) {
            $query->where('unit_category_id', (string) $filters['unit_category_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('unit_category_id')
            ->orderByDesc('is_base')
            ->orderBy('name');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Unit>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Unit>
     */
    public function options(array $filters = []): Collection
    {
        $query = Unit::query()->active()->orderBy('name');

        if (! empty($filters['unit_category_id'])) {
            $query->where('unit_category_id', (string) $filters['unit_category_id']);
        }

        return $query->get(['id', 'unit_category_id', 'name', 'symbol', 'is_base', 'conversion_factor', 'allow_decimals', 'sunat_code']);
    }

    public function findById(string $id): ?Unit
    {
        return Unit::query()->with('category')->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Unit
    {
        return DB::transaction(function () use ($attributes) {
            $categoryId = (string) $attributes['unit_category_id'];

            if ($this->isPackagingCategory($categoryId)) {
                return Unit::create($this->asPackagingUnit($attributes));
            }

            $hasBase = Unit::where('unit_category_id', $categoryId)->where('is_base', true)->exists();
            $isBase = ! empty($attributes['is_base']);

            if ($isBase) {
                if ($hasBase) {
                    throw new InventoryRuleException('La categoría de unidad ya cuenta con una unidad base.', 'is_base');
                }
                $attributes['conversion_factor'] = '1.000000';
            } else {
                if (! $hasBase) {
                    throw new InventoryRuleException('La categoría debe tener primero una unidad base antes de crear unidades derivadas.', 'is_base');
                }
            }

            return Unit::create($attributes);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Unit $unit, array $attributes): Unit
    {
        return DB::transaction(function () use ($unit, $attributes) {
            $categoryId = (string) ($attributes['unit_category_id'] ?? $unit->unit_category_id);

            if ($this->isPackagingCategory($categoryId)) {
                if ($unit->is_base) {
                    throw new InventoryRuleException('Una unidad base no puede pasar a una categoría de empaque.', 'unit_category_id');
                }

                $unit->update($this->asPackagingUnit($attributes));

                return $unit->fresh() ?? $unit;
            }

            if (array_key_exists('is_base', $attributes)) {
                $newIsBase = (bool) $attributes['is_base'];

                if ($unit->is_base && ! $newIsBase) {
                    throw new InventoryRuleException('La categoría de unidad debe conservar una unidad base.', 'is_base');
                }

                if (! $unit->is_base && $newIsBase) {
                    $otherBase = Unit::where('unit_category_id', $categoryId)
                        ->where('id', '!=', $unit->id)
                        ->where('is_base', true)
                        ->exists();

                    if ($otherBase) {
                        throw new InventoryRuleException('La categoría de unidad ya cuenta con una unidad base.', 'is_base');
                    }

                    $attributes['conversion_factor'] = '1.000000';
                }
            }

            if ($unit->is_base && (! array_key_exists('is_base', $attributes) || $attributes['is_base'])) {
                $attributes['conversion_factor'] = '1.000000';
            }

            $unit->update($attributes);

            return $unit->fresh() ?? $unit;
        });
    }

    public function delete(Unit $unit): bool
    {
        if (CatalogItem::where('base_unit_id', $unit->id)->exists()) {
            throw new InventoryRuleException('No se puede eliminar una unidad asignada como unidad base de ítems del catálogo.', 'unit');
        }

        if (ItemPresentation::where('unit_id', $unit->id)->exists()) {
            throw new InventoryRuleException('No se puede eliminar una unidad utilizada en presentaciones de ítems del catálogo.', 'unit');
        }

        if ($unit->is_base) {
            $otherUnits = Unit::where('unit_category_id', $unit->unit_category_id)
                ->where('id', '!=', $unit->id)
                ->exists();

            if ($otherUnits) {
                throw new InventoryRuleException('No se puede eliminar la unidad base mientras existan otras unidades en la categoría.', 'unit');
            }
        }

        return (bool) $unit->delete();
    }

    /**
     * Convierte una cantidad de una unidad a otra de la misma categoría.
     */
    public function convert(float $quantity, Unit $from, Unit $to): float
    {
        if ($from->unit_category_id !== $to->unit_category_id) {
            throw new InventoryRuleException('No se puede convertir entre unidades de distintas categorías.', 'units');
        }

        if ($this->isPackagingCategory($from->unit_category_id)) {
            throw new InventoryRuleException('Las unidades de empaque no tienen un factor fijo: la conversión se define en la presentación de cada producto.', 'units');
        }

        $fromFactor = (float) $from->conversion_factor;
        $toFactor = (float) $to->conversion_factor;

        if ($toFactor <= 0) {
            throw new InventoryRuleException('Factor de conversión inválido.', 'to_unit_id');
        }

        return round($quantity * $fromFactor / $toFactor, 6);
    }

    private function isPackagingCategory(string $categoryId): bool
    {
        return (bool) UnitCategory::query()->whereKey($categoryId)->value('is_packaging');
    }

    /**
     * Las unidades de empaque nunca son base y guardan factor 1: el factor real vive en la presentación.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function asPackagingUnit(array $attributes): array
    {
        return array_merge($attributes, ['is_base' => false, 'conversion_factor' => '1.000000']);
    }
}
