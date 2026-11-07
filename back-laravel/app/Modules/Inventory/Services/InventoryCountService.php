<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Enums\InventoryCountStatus;
use App\Modules\Inventory\Enums\SystemReason;
use App\Modules\Inventory\Enums\WarehouseNoteType;
use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\InventoryCount;
use App\Modules\Inventory\Models\InventoryCountItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseReason;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InventoryCountService
{
    public function __construct(
        protected WarehouseNoteService $noteService,
        protected WarehouseNoteApprovalService $approvalService
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $accessibleBranchIds
     * @return Builder<InventoryCount>
     */
    public function query(array $filters, array $accessibleBranchIds, ?Branch $activeBranch = null): Builder
    {
        $query = InventoryCount::query()
            ->with(['branch', 'warehouse', 'category', 'creator', 'closer', 'canceller'])
            ->withCount([
                'items',
                'items as counted_items_count' => fn (Builder $q) => $q->whereNotNull('counted_quantity'),
            ]);

        if ($activeBranch !== null) {
            $query->where('branch_id', $activeBranch->id);
        } else {
            $query->whereIn('branch_id', $accessibleBranchIds);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (string) $filters['warehouse_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (string) $filters['category_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('count_date', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('count_date', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('number', 'ilike', "%{$search}%")
                    ->orWhere('notes', 'ilike', "%{$search}%");
            });
        }

        return $query->orderByDesc('count_date')->orderByDesc('created_at');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, Branch $activeBranch): InventoryCount
    {
        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);

        if ($warehouse->branch_id !== $activeBranch->id) {
            throw new InventoryRuleException('El almacén seleccionado no pertenece a la sede activa.', 'warehouse_id');
        }

        if (! $warehouse->is_active) {
            throw new InventoryRuleException('El almacén seleccionado se encuentra inactivo.', 'warehouse_id');
        }

        return DB::transaction(function () use ($data, $actor, $warehouse) {
            // Validar que no exista otro conteo en estado 'counting' para el almacén
            $existingCounting = InventoryCount::where('warehouse_id', $warehouse->id)
                ->where('status', InventoryCountStatus::Counting->value)
                ->lockForUpdate()
                ->exists();

            if ($existingCounting) {
                throw new InventoryRuleException('Ya existe una toma de inventario en proceso para este almacén.', 'warehouse_id');
            }

            $number = SequentialCodeGenerator::next('inventory_count', 'TI');

            /** @var InventoryCount $count */
            $count = InventoryCount::create([
                'number' => $number,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'category_id' => $data['category_id'] ?? null,
                'count_date' => $data['count_date'],
                'status' => InventoryCountStatus::Counting,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            // Población de líneas desde stocks
            $stockQuery = Stock::with(['catalogItem.baseUnit'])
                ->where('warehouse_id', $warehouse->id);

            if (! empty($data['category_id'])) {
                $categoryIds = CategoryService::descendantIds((string) $data['category_id']);
                $stockQuery->whereHas('catalogItem', fn ($q) => $q->whereIn('category_id', $categoryIds));
            }

            $stocks = $stockQuery->get();

            foreach ($stocks as $stock) {
                $catItem = $stock->catalogItem;
                if ($catItem && $catItem->track_stock && $catItem->is_active) {
                    $count->items()->create([
                        'catalog_item_id' => $stock->catalog_item_id,
                        'system_quantity' => (float) $stock->quantity,
                        'unit_cost' => (float) $stock->avg_cost,
                        'counted_quantity' => null,
                    ]);
                }
            }

            return $count->load(['branch', 'warehouse', 'category', 'creator', 'items.catalogItem.baseUnit']);
        });
    }

    /**
     * Actualiza cantidades contadas en lote o agrega ítems nuevos detectados físicamente.
     *
     * @param  list<array{catalog_item_id: string, counted_quantity: float}>  $rows
     */
    public function updateItems(InventoryCount $count, array $rows, User $actor): InventoryCount
    {
        if (! $count->isCounting()) {
            throw new InventoryRuleException('Solo se pueden actualizar conteos en proceso.', 'status');
        }

        return DB::transaction(function () use ($count, $rows, $actor) {
            foreach ($rows as $row) {
                $catalogItemId = $row['catalog_item_id'];
                $countedQty = (float) $row['counted_quantity'];

                /** @var InventoryCountItem|null $item */
                $item = $count->items()->where('catalog_item_id', $catalogItemId)->first();

                if ($item !== null) {
                    $item->update([
                        'counted_quantity' => $countedQty,
                        'counted_by' => $actor->id,
                        'counted_at' => now(),
                    ]);
                } else {
                    /** @var CatalogItem $catItem */
                    $catItem = CatalogItem::findOrFail($catalogItemId);

                    if (! $catItem->track_stock || ! $catItem->is_active) {
                        throw new InventoryRuleException("El artículo '{$catItem->name}' no registra stock o está inactivo.", 'items');
                    }

                    $count->items()->create([
                        'catalog_item_id' => $catalogItemId,
                        'system_quantity' => 0.0,
                        'unit_cost' => (float) ($catItem->last_purchase_cost ?? 0.0),
                        'counted_quantity' => $countedQty,
                        'counted_by' => $actor->id,
                        'counted_at' => now(),
                    ]);
                }
            }

            return $count->refresh()->load(['branch', 'warehouse', 'category', 'items.catalogItem.baseUnit', 'items.counter']);
        });
    }

    /**
     * Cierra la toma física, calcula diferencias y genera las notas de ajuste correspondientes.
     */
    public function close(InventoryCount $count, bool $uncountedAsZero, User $actor): InventoryCount
    {
        return DB::transaction(function () use ($count, $uncountedAsZero, $actor) {
            /** @var InventoryCount $lockedCount */
            $lockedCount = InventoryCount::where('id', $count->id)->lockForUpdate()->firstOrFail();

            if (! $lockedCount->isCounting()) {
                throw new InventoryRuleException('Solo se pueden cerrar tomas de inventario en proceso.', 'status');
            }

            $uncountedCount = $lockedCount->items()->whereNull('counted_quantity')->count();
            if ($uncountedCount > 0) {
                if (! $uncountedAsZero) {
                    throw new InventoryRuleException("Existen {$uncountedCount} ítems pendientes de conteo.", 'items');
                }

                $lockedCount->items()->whereNull('counted_quantity')->update([
                    'counted_quantity' => 0.0,
                    'counted_by' => $actor->id,
                    'counted_at' => now(),
                ]);
            }

            $items = $lockedCount->items()->with(['catalogItem.baseUnit', 'catalogItem.presentations'])->get();

            $inLines = [];
            $outLines = [];

            foreach ($items as $item) {
                $stock = Stock::where('warehouse_id', $lockedCount->warehouse_id)
                    ->where('catalog_item_id', $item->catalog_item_id)
                    ->first();

                $closingSystemQty = $stock !== null ? (float) $stock->quantity : 0.0;
                $countedQty = (float) $item->counted_quantity;
                $diff = round($countedQty - $closingSystemQty, 4);

                $avgCost = $stock !== null ? (float) $stock->avg_cost : 0.0;
                if ($avgCost <= 0.0) {
                    $avgCost = (float) ($item->catalogItem->last_purchase_cost ?? 0.0);
                }

                $diffValue = round($diff * $avgCost, 4);

                $item->update([
                    'closing_system_quantity' => $closingSystemQty,
                    'difference' => $diff,
                    'unit_cost' => $avgCost,
                    'difference_value' => $diffValue,
                ]);

                if ($diff > 0) {
                    /** @var ItemPresentation $basePres */
                    $basePres = $item->catalogItem->presentations()->where('is_base', true)->firstOrFail();
                    $inLines[] = [
                        'item_presentation_id' => $basePres->id,
                        'quantity' => $diff,
                        'unit_cost' => $avgCost,
                        'notes' => "Ajuste sobrante por toma de inventario {$lockedCount->number}",
                    ];
                } elseif ($diff < 0) {
                    /** @var ItemPresentation $basePres */
                    $basePres = $item->catalogItem->presentations()->where('is_base', true)->firstOrFail();
                    $outLines[] = [
                        'item_presentation_id' => $basePres->id,
                        'quantity' => abs($diff),
                        'notes' => "Ajuste faltante por toma de inventario {$lockedCount->number}",
                    ];
                }
            }

            // Generar y aprobar notas de ajuste
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::findOrFail($lockedCount->warehouse_id);
            /** @var Branch $branch */
            $branch = Branch::findOrFail($lockedCount->branch_id);

            if (! empty($inLines)) {
                $reasonIn = WarehouseReason::where('code', SystemReason::CountAdjustmentIn->value)->firstOrFail();
                $noteIn = $this->noteService->create([
                    'type' => WarehouseNoteType::In->value,
                    'warehouse_id' => $warehouse->id,
                    'reason_id' => $reasonIn->id,
                    'inventory_count_id' => $lockedCount->id,
                    'document_date' => $lockedCount->count_date->format('Y-m-d'),
                    'reference_document' => $lockedCount->number,
                    'observation' => "Ajuste de ingreso generado por toma de inventario {$lockedCount->number}",
                    'items' => $inLines,
                ], $actor, $branch);

                $this->approvalService->approve($noteIn, $actor);
            }

            if (! empty($outLines)) {
                $reasonOut = WarehouseReason::where('code', SystemReason::CountAdjustmentOut->value)->firstOrFail();
                $noteOut = $this->noteService->create([
                    'type' => WarehouseNoteType::Out->value,
                    'warehouse_id' => $warehouse->id,
                    'reason_id' => $reasonOut->id,
                    'inventory_count_id' => $lockedCount->id,
                    'document_date' => $lockedCount->count_date->format('Y-m-d'),
                    'reference_document' => $lockedCount->number,
                    'observation' => "Ajuste de salida generado por toma de inventario {$lockedCount->number}",
                    'items' => $outLines,
                ], $actor, $branch);

                $this->approvalService->approve($noteOut, $actor);
            }

            $lockedCount->update([
                'status' => InventoryCountStatus::Closed,
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ]);

            return $lockedCount->refresh()->load([
                'branch',
                'warehouse',
                'category',
                'creator',
                'closer',
                'items.catalogItem.baseUnit',
                'items.counter',
                'warehouseNotes.items',
                'warehouseNotes.reason',
            ]);
        });
    }

    /**
     * Anula una toma física de inventario. Si ya estaba cerrada, anula también sus notas de almacén asociadas.
     */
    public function cancel(InventoryCount $count, User $actor): InventoryCount
    {
        return DB::transaction(function () use ($count, $actor) {
            /** @var InventoryCount $lockedCount */
            $lockedCount = InventoryCount::where('id', $count->id)->lockForUpdate()->firstOrFail();

            if ($lockedCount->isCancelled()) {
                throw new InventoryRuleException('La toma de inventario ya se encuentra anulada.', 'status');
            }

            if ($lockedCount->isClosed()) {
                // Anular todas las notas generadas
                foreach ($lockedCount->warehouseNotes as $note) {
                    if (! $note->isCancelled()) {
                        $this->approvalService->cancel(
                            $note,
                            "Anulación de toma física {$lockedCount->number}",
                            $actor,
                            fromCount: true
                        );
                    }
                }
            }

            $lockedCount->update([
                'status' => InventoryCountStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            return $lockedCount->refresh()->load([
                'branch',
                'warehouse',
                'category',
                'creator',
                'closer',
                'canceller',
                'items.catalogItem.baseUnit',
                'warehouseNotes',
            ]);
        });
    }
}
