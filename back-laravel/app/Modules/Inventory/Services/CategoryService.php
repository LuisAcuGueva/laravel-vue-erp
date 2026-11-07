<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Category>
     */
    public function query(array $filters = []): Builder
    {
        $query = Category::query()->with('parent.parent')->withCount('children');

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (array_key_exists('parent_id', $filters)) {
            if ($filters['parent_id'] === 'root' || $filters['parent_id'] === null) {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', (string) $filters['parent_id']);
            }
        }

        if (array_key_exists('depth', $filters) && $filters['depth'] !== null) {
            $query->where('depth', (int) $filters['depth']);
        }

        // Orden jerárquico (máximo 3 niveles): raíz, luego nivel 1 y luego nivel 2, cada uno por nombre.
        $parentName = '(SELECT p.name FROM categories p WHERE p.id = categories.parent_id)';
        $grandparentName = '(SELECT gp.name FROM categories p JOIN categories gp ON gp.id = p.parent_id WHERE p.id = categories.parent_id)';

        return $query
            ->orderByRaw("COALESCE({$grandparentName}, {$parentName}, categories.name)")
            ->orderByRaw("CASE WHEN categories.depth = 0 THEN '' WHEN categories.depth = 1 THEN categories.name ELSE {$parentName} END")
            ->orderByRaw("CASE WHEN categories.depth = 2 THEN categories.name ELSE '' END");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Category>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Devuelve el árbol completo de categorías activas en una sola consulta.
     *
     * @return Collection<int, Category>
     */
    public function tree(): Collection
    {
        /** @var Collection<int, Category> $all */
        $all = Category::query()->active()->orderBy('name')->get();

        $grouped = $all->groupBy('parent_id');

        foreach ($all as $item) {
            $item->setRelation('children', $grouped->get($item->id, new Collection));
        }

        return $all->filter(fn (Category $item) => $item->parent_id === null)->values();
    }

    /**
     * Devuelve las opciones activas para selects.
     *
     * @return Collection<int, Category>
     */
    public function options(): Collection
    {
        return Category::query()->active()->orderBy('depth')->orderBy('name')->get(['id', 'parent_id', 'code', 'name', 'depth']);
    }

    public function findById(string $id): ?Category
    {
        return Category::query()->with('parent.parent')->withCount('children')->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Category
    {
        $parentId = $attributes['parent_id'] ?? null;

        if ($parentId !== null && $parentId !== '') {
            $parent = Category::where('id', $parentId)->first();

            if ($parent === null || ! $parent->is_active) {
                throw new InventoryRuleException('La categoría padre especificada no existe o está inactiva.', 'parent_id');
            }

            if ($parent->depth >= 2) {
                throw new InventoryRuleException('No se permite superar la profundidad máxima de 3 niveles.', 'parent_id');
            }

            $attributes['depth'] = $parent->depth + 1;
        } else {
            $attributes['depth'] = 0;
            $attributes['parent_id'] = null;
        }

        return Category::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Category $category, array $attributes): Category
    {
        return DB::transaction(function () use ($category, $attributes) {
            if (array_key_exists('parent_id', $attributes)) {
                $newParentId = $attributes['parent_id'];

                if ($newParentId !== $category->parent_id) {
                    if ($newParentId === $category->id) {
                        throw new InventoryRuleException('Una categoría no puede ser su propio ancestro (ciclo detectado).', 'parent_id');
                    }

                    if ($newParentId !== null && $newParentId !== '') {
                        $newParent = Category::where('id', $newParentId)->first();

                        if ($newParent === null || ! $newParent->is_active) {
                            throw new InventoryRuleException('La categoría padre especificada no existe o está inactiva.', 'parent_id');
                        }

                        if (in_array($category->id, $newParent->ancestorIds(), true)) {
                            throw new InventoryRuleException('Una categoría no puede ser su propio ancestro (ciclo detectado).', 'parent_id');
                        }

                        $maxSubtreeDepth = $this->calculateMaxSubtreeDepth($category);
                        $relativeDepth = $maxSubtreeDepth - $category->depth;
                        $newDepth = $newParent->depth + 1;

                        if ($newDepth + $relativeDepth > 2) {
                            throw new InventoryRuleException('El movimiento excede la profundidad máxima permitida de 3 niveles.', 'parent_id');
                        }
                    } else {
                        $newDepth = 0;
                    }

                    $depthDelta = $newDepth - $category->depth;
                    $attributes['depth'] = $newDepth;

                    if ($depthDelta !== 0) {
                        $this->updateDescendantDepths($category, $depthDelta);
                    }
                }
            }

            $category->update($attributes);

            return $category->fresh() ?? $category;
        });
    }

    /**
     * Devuelve los IDs de la categoría dada y de todos sus descendientes en el árbol.
     *
     * @return array<int, string>
     */
    public static function descendantIds(string $categoryId): array
    {
        $ids = [$categoryId];
        $childrenIds = Category::where('parent_id', $categoryId)->pluck('id')->all();

        while (! empty($childrenIds)) {
            $ids = array_merge($ids, $childrenIds);
            $childrenIds = Category::whereIn('parent_id', $childrenIds)->pluck('id')->all();
        }

        return array_values(array_unique($ids));
    }

    public function delete(Category $category): bool
    {
        if ($category->children()->exists()) {
            throw new InventoryRuleException('No se puede eliminar una categoría que tiene subcategorías asociadas.', 'category');
        }

        if (CatalogItem::where('category_id', $category->id)->exists()) {
            throw new InventoryRuleException('No se puede eliminar una categoría que contiene ítems registrados.', 'category');
        }

        return (bool) $category->delete();
    }

    private function calculateMaxSubtreeDepth(Category $category): int
    {
        $max = $category->depth;

        foreach ($category->children as $child) {
            $childMax = $this->calculateMaxSubtreeDepth($child);
            if ($childMax > $max) {
                $max = $childMax;
            }
        }

        return $max;
    }

    private function updateDescendantDepths(Category $parent, int $depthDelta): void
    {
        foreach ($parent->children as $child) {
            $child->depth += $depthDelta;
            $child->save();
            $this->updateDescendantDepths($child, $depthDelta);
        }
    }
}
