<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Enums\CatalogItemType;
use App\Modules\Inventory\Enums\InventoryCountStatus;
use App\Modules\Inventory\Enums\WarehouseNoteStatus;
use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\InventoryCountItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\ItemPrice;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\WarehouseNoteItem;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseRequestItem;
use App\Modules\Sales\Models\QuotationItem;
use App\Modules\Sales\Models\SaleItem;
use App\Support\Numbering\SequentialCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CatalogItemService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CatalogItem>
     */
    public function query(array $filters = []): Builder
    {
        $query = CatalogItem::query()
            ->with([
                'category',
                'brand',
                'baseUnit',
                'defaultPresentation.unit',
                'defaultPresentation.prices',
            ]);

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        if (! empty($filters['type'])) {
            $query->ofType((string) $filters['type']);
        }

        if (! empty($filters['category_id'])) {
            $query->inCategoryTree((string) $filters['category_id']);
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', (string) $filters['brand_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (array_key_exists('is_sellable', $filters) && $filters['is_sellable'] !== null) {
            $query->where('is_sellable', (bool) $filters['is_sellable']);
        }

        if (array_key_exists('is_purchasable', $filters) && $filters['is_purchasable'] !== null) {
            $query->where('is_purchasable', (bool) $filters['is_purchasable']);
        }

        if (array_key_exists('track_stock', $filters) && $filters['track_stock'] !== null) {
            $query->where('track_stock', (bool) $filters['track_stock']);
        }

        return $query->orderBy('name');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CatalogItem>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return $this->query($filters)->paginate($perPage);
    }

    public function find(string $id): CatalogItem
    {
        return CatalogItem::with([
            'category',
            'brand',
            'baseUnit',
            'defaultPresentation.unit',
            'defaultPresentation.prices',
            'presentations.unit',
            'presentations.prices',
        ])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $prices
     */
    public function create(array $attributes, array $prices = []): CatalogItem
    {
        return DB::transaction(function () use ($attributes, $prices): CatalogItem {
            if (empty($attributes['code'])) {
                $attributes['code'] = SequentialCodeGenerator::next('catalog_item', 'ITM');
            }

            $type = $attributes['type'] instanceof CatalogItemType
                ? $attributes['type']
                : CatalogItemType::from((string) $attributes['type']);

            if ($type === CatalogItemType::Service) {
                $attributes['track_stock'] = false;
            }

            /** @var Unit $baseUnit */
            $baseUnit = Unit::findOrFail($attributes['base_unit_id']);

            /** @var CatalogItem $item */
            $item = CatalogItem::create($attributes);

            /** @var ItemPresentation $presentation */
            $presentation = ItemPresentation::create([
                'catalog_item_id' => $item->id,
                'unit_id' => $baseUnit->id,
                'name' => $baseUnit->name,
                'factor' => 1.0000,
                'barcode' => $attributes['barcode'] ?? null,
                'is_base' => true,
                'is_default' => true,
                'is_active' => true,
            ]);

            if (! empty($prices)) {
                foreach ($prices as $p) {
                    ItemPrice::create([
                        'item_presentation_id' => $presentation->id,
                        'price_type' => $p['price_type'],
                        'price' => $p['price'],
                        'min_quantity' => $p['min_quantity'] ?? 1,
                    ]);
                }
            }

            return $this->find($item->id);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CatalogItem $item, array $attributes): CatalogItem
    {
        return DB::transaction(function () use ($item, $attributes): CatalogItem {
            if (array_key_exists('type', $attributes)) {
                $newType = $attributes['type'] instanceof CatalogItemType
                    ? $attributes['type']
                    : CatalogItemType::from((string) $attributes['type']);

                if ($item->type === CatalogItemType::Product && $newType === CatalogItemType::Service) {
                    if ($this->hasMovements($item)) {
                        throw new InventoryRuleException('No se puede cambiar un producto con movimientos a servicio.', 'type');
                    }
                    $attributes['track_stock'] = false;
                }
            }

            if (array_key_exists('base_unit_id', $attributes) && $attributes['base_unit_id'] !== $item->base_unit_id) {
                if ($this->hasMovements($item)) {
                    throw new InventoryRuleException('No se puede cambiar la unidad base de un ítem con movimientos de stock.', 'base_unit_id');
                }

                /** @var Unit $newUnit */
                $newUnit = Unit::findOrFail($attributes['base_unit_id']);

                $basePres = $item->presentations()->where('is_base', true)->first();
                if ($basePres !== null) {
                    $basePres->update([
                        'unit_id' => $newUnit->id,
                        'name' => $newUnit->name,
                    ]);
                }
            }

            $barcodeProvided = array_key_exists('barcode', $attributes);
            $barcode = $attributes['barcode'] ?? null;
            unset($attributes['barcode']);

            $item->update($attributes);

            if ($barcodeProvided) {
                $item->presentations()->where('is_base', true)->update(['barcode' => $barcode]);
            }

            return $this->find($item->id);
        });
    }

    public function delete(CatalogItem $item): bool
    {
        if ($this->hasMovements($item)) {
            throw new InventoryRuleException('No se puede eliminar un ítem con movimientos de inventario.', 'item');
        }

        if (WarehouseNoteItem::where('catalog_item_id', $item->id)->whereHas('warehouseNote', fn ($q) => $q->where('status', WarehouseNoteStatus::Draft->value))->exists()) {
            throw new InventoryRuleException('No se puede eliminar un ítem incluido en notas de almacén en borrador.', 'item');
        }

        if (InventoryCountItem::where('catalog_item_id', $item->id)->whereHas('inventoryCount', fn ($q) => $q->where('status', InventoryCountStatus::Counting->value))->exists()) {
            throw new InventoryRuleException('No se puede eliminar un ítem incluido en tomas de inventario en proceso.', 'item');
        }

        if (PurchaseOrderItem::where('catalog_item_id', $item->id)->exists() || PurchaseRequestItem::where('catalog_item_id', $item->id)->exists()) {
            throw new InventoryRuleException('No se puede eliminar un ítem incluido en requerimientos u órdenes de compra.', 'item');
        }

        if (QuotationItem::where('catalog_item_id', $item->id)->exists()) {
            throw new InventoryRuleException('No se puede eliminar un ítem incluido en cotizaciones.', 'item');
        }

        if (SaleItem::where('catalog_item_id', $item->id)->exists()) {
            throw new InventoryRuleException('No se puede eliminar un ítem incluido en ventas.', 'item');
        }

        return DB::transaction(function () use ($item): bool {
            $deleted = (bool) $item->delete();

            // Las presentaciones (y su barcode único) deben borrarse con el ítem:
            // de lo contrario quedan "vivas" y bloquean la reutilización de su barcode.
            if ($deleted) {
                $item->presentations()->delete();
            }

            return $deleted;
        });
    }

    /**
     * Comprueba si el ítem tiene movimientos registrados de inventario.
     */
    public function hasMovements(CatalogItem $item): bool
    {
        return KardexMovement::where('catalog_item_id', $item->id)->exists();
    }
}
