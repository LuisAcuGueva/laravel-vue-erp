<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\ItemPresentation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CatalogLookupService
{
    protected ?string $warehouseId = null;

    public function __construct(
        protected StockQueryService $stockQueryService
    ) {}

    /**
     * @return Collection<int, ItemPresentation>
     */
    public function search(string $q, string $context, int $limit = 20, ?string $warehouseId = null): Collection
    {
        $term = trim($q);
        $safeLimit = min(max($limit, 1), 50);

        $query = ItemPresentation::query()
            ->with(['item', 'unit', 'prices'])
            ->where('item_presentations.is_active', true)
            ->whereHas('item', function (Builder $itemQuery) use ($context) {
                $itemQuery->where('is_active', true);

                if ($context === 'sale') {
                    $itemQuery->where('is_sellable', true);
                } elseif ($context === 'purchase') {
                    $itemQuery->where('is_purchasable', true);
                } elseif ($context === 'stock') {
                    $itemQuery->where('track_stock', true);
                }
            });

        $driver = $query->getConnection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $sub) use ($term, $like, $operator) {
            $sub->where('item_presentations.barcode', $term)
                ->orWhere('item_presentations.barcode', $operator, $like)
                ->orWhere('item_presentations.name', $operator, $like)
                ->orWhereHas('item', function (Builder $itemSub) use ($term, $like, $operator) {
                    $itemSub->where('code', $term)
                        ->orWhere('code', $operator, $like)
                        ->orWhere('name', $operator, $like);
                });
        });

        $query->join('catalog_items', 'catalog_items.id', '=', 'item_presentations.catalog_item_id')
            ->select('item_presentations.*')
            ->orderByRaw(
                'CASE WHEN item_presentations.barcode = ? THEN 0 WHEN catalog_items.code = ? THEN 1 ELSE 2 END',
                [$term, $term]
            )
            ->orderBy('catalog_items.name')
            ->orderBy('item_presentations.factor');

        $activeWarehouseId = $warehouseId ?? $this->warehouseId;
        $this->warehouseId = null;

        /** @var Collection<int, ItemPresentation> $results */
        $results = $query->take($safeLimit)->get();

        if ($activeWarehouseId !== null) {
            /** @var list<string> $catalogItemIds */
            $catalogItemIds = array_values(array_map('strval', $results->pluck('catalog_item_id')->unique()->all()));
            $availableMap = $this->stockQueryService->availableFor($activeWarehouseId, $catalogItemIds);

            foreach ($results as $presentation) {
                $baseQty = $availableMap[$presentation->catalog_item_id] ?? 0.0;
                $factor = (float) $presentation->factor;
                $presQty = $factor > 0 ? round($baseQty / $factor, 4) : $baseQty;

                $presentation->setAttribute('stock', [
                    'available' => (float) $baseQty,
                    'available_presentation' => (float) $presQty,
                ]);
            }
        }

        return $results;
    }

    /**
     * Inyecta almacén para consultar stock disponible.
     */
    public function withStock(?string $warehouseId = null): self
    {
        $this->warehouseId = $warehouseId;

        return $this;
    }
}
