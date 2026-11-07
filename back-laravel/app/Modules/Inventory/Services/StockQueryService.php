<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StockQueryService
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $accessibleWarehouseIds
     * @return array{paginator: LengthAwarePaginator<int, Stock>, total_value: float}
     */
    public function list(array $filters, array $accessibleWarehouseIds): array
    {
        $query = Stock::query()
            ->with(['warehouse', 'catalogItem.baseUnit', 'catalogItem.category']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        } else {
            $query->whereIn('warehouse_id', $accessibleWarehouseIds);
        }

        if (! empty($filters['category_id'])) {
            $descendants = $this->categoryService->descendantIds((string) $filters['category_id']);
            $categoryIds = array_merge([(string) $filters['category_id']], $descendants);

            $query->whereHas('catalogItem', function ($q) use ($categoryIds) {
                $q->whereIn('category_id', $categoryIds);
            });
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->whereHas('catalogItem', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        if (! empty($filters['below_min']) && filter_var($filters['below_min'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereRaw('stocks.quantity < stocks.min_stock');
        }

        $includeZero = isset($filters['include_zero']) ? filter_var($filters['include_zero'], FILTER_VALIDATE_BOOLEAN) : false;
        if (! $includeZero) {
            $query->where('stocks.quantity', '!=', 0);
        }

        // Total valorizado del conjunto filtrado antes de paginar
        $totalValue = (float) ((clone $query)->selectRaw('COALESCE(SUM(quantity * avg_cost), 0) as total_val')->value('total_val') ?? 0.0);

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 15;
        $paginator = $query->orderByDesc('last_movement_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'paginator' => $paginator,
            'total_value' => round($totalValue, 2),
        ];
    }

    /**
     * Retorna el stock de un ítem en todos los almacenes accesibles.
     *
     * @param  list<string>  $accessibleWarehouseIds
     * @return list<array<string, mixed>>
     */
    public function byItem(string $catalogItemId, array $accessibleWarehouseIds): array
    {
        /** @var CatalogItem $item */
        $item = CatalogItem::with('defaultPresentation')->findOrFail($catalogItemId);
        $factor = (float) ($item->defaultPresentation->factor ?? 1.0);

        $warehouses = Warehouse::with('branch')
            ->whereIn('id', $accessibleWarehouseIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $stocks = Stock::where('catalog_item_id', $catalogItemId)
            ->whereIn('warehouse_id', $accessibleWarehouseIds)
            ->get()
            ->keyBy('warehouse_id');

        $result = [];
        foreach ($warehouses as $warehouse) {
            $stock = $stocks->get($warehouse->id);
            $qty = (float) ($stock->quantity ?? 0.0);

            $presQty = $factor > 0 ? round($qty / $factor, 4) : $qty;

            $result[] = [
                'warehouse' => [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                    'branch' => [
                        'id' => $warehouse->branch->id,
                        'name' => $warehouse->branch->name,
                    ],
                ],
                'quantity' => $qty,
                'presentation_quantity' => $presQty,
            ];
        }

        return $result;
    }

    /**
     * Retorna un mapa [catalog_item_id => stock_disponible] para un almacén.
     *
     * @param  list<string>  $catalogItemIds
     * @return array<string, float>
     */
    public function availableFor(string $warehouseId, array $catalogItemIds): array
    {
        $existing = Stock::where('warehouse_id', $warehouseId)
            ->whereIn('catalog_item_id', $catalogItemIds)
            ->pluck('quantity', 'catalog_item_id')
            ->all();

        $result = [];
        foreach ($catalogItemIds as $id) {
            $result[$id] = (float) ($existing[$id] ?? 0.0);
        }

        return $result;
    }

    /**
     * Actualiza o registra los límites mínimo y máximo de stock.
     */
    public function upsertLimits(string $warehouseId, string $catalogItemId, float $minStock, ?float $maxStock, ?User $causer = null): Stock
    {
        return DB::transaction(function () use ($warehouseId, $catalogItemId, $minStock, $maxStock, $causer): Stock {
            /** @var Stock $stock */
            $stock = Stock::firstOrNew(['warehouse_id' => $warehouseId, 'catalog_item_id' => $catalogItemId]);
            $wasNew = ! $stock->exists;
            $old = ['min_stock' => $stock->min_stock, 'max_stock' => $stock->max_stock];

            $stock->fill(['min_stock' => $minStock, 'max_stock' => $maxStock]);
            $changed = array_keys($stock->getDirty());
            $stock->save();

            if ($changed !== []) {
                // Stock no usa LogsActivity: el kardex crea/actualiza filas en cada movimiento
                activity()
                    ->performedOn($stock)
                    ->causedBy($causer)
                    ->event($wasNew ? 'created' : 'updated')
                    ->withProperties([
                        'attributes' => array_intersect_key($stock->only(['min_stock', 'max_stock']), array_flip($changed)),
                        'old' => $wasNew ? [] : array_intersect_key($old, array_flip($changed)),
                    ])
                    ->log('Límites de stock actualizados');
            }

            return $stock;
        })->load(['warehouse', 'catalogItem.baseUnit', 'catalogItem.category']);
    }
}
