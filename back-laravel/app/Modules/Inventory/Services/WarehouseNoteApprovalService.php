<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\SystemReason;
use App\Modules\Inventory\Enums\WarehouseNoteStatus;
use App\Modules\Inventory\Enums\WarehouseNoteType;
use App\Modules\Inventory\Enums\WarehouseReasonType;
use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\WarehouseNote;
use App\Modules\Inventory\Models\WarehouseNoteItem;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseNoteApprovalService
{
    public function __construct(
        protected StockLedgerService $stockLedgerService
    ) {}

    /**
     * Aprueba una nota de almacén en borrador y aplica sus movimientos al ledger de stock y kardex.
     */
    public function approve(WarehouseNote $note, User $actor): WarehouseNote
    {
        return DB::transaction(function () use ($note, $actor): WarehouseNote {
            /** @var WarehouseNote $lockedNote */
            $lockedNote = WarehouseNote::where('id', $note->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedNote->isDraft()) {
                throw new InventoryRuleException('Solo se pueden aprobar notas en estado borrador.', 'status');
            }

            $lockedNote->loadMissing(['warehouse', 'targetWarehouse', 'reason', 'items.catalogItem', 'items.presentation']);

            $reason = $lockedNote->reason;
            $items = $lockedNote->items->sortBy('line');
            $movedAt = Carbon::parse($lockedNote->document_date)->setTimeFrom(now());

            foreach ($items as $line) {
                $catalogItem = $line->catalogItem;
                $presentation = $line->presentation;
                $factor = (float) $presentation->factor;

                if ($lockedNote->type === WarehouseNoteType::In) {
                    $movementType = match (true) {
                        $reason->code === SystemReason::Opening->value => MovementType::Opening,
                        $reason->type === WarehouseReasonType::Adjustment => MovementType::AdjustmentIn,
                        default => MovementType::NoteIn,
                    };

                    $unitCostPerBase = $factor > 0 ? (float) ($line->unit_cost ?? 0.0) / $factor : 0.0;

                    $movementData = new StockMovementData(
                        warehouseId: $lockedNote->warehouse_id,
                        catalogItemId: $catalogItem->id,
                        baseQuantity: (float) $line->base_quantity,
                        type: $movementType,
                        source: $lockedNote,
                        sourceNumber: $lockedNote->number,
                        unitCost: round($unitCostPerBase, 6),
                        presentationId: $presentation->id,
                        presentationQuantity: (float) $line->quantity,
                        reasonCode: $reason->code,
                        notes: $line->notes ?? $lockedNote->observation,
                        movedAt: $movedAt,
                    );

                    $movement = $this->stockLedgerService->in($movementData);

                    $line->update([
                        'base_unit_cost' => $movement->unit_cost,
                        'total_cost' => $movement->total_cost,
                    ]);
                } elseif ($lockedNote->type === WarehouseNoteType::Out) {
                    $movementType = $reason->type === WarehouseReasonType::Adjustment
                        ? MovementType::AdjustmentOut
                        : MovementType::NoteOut;

                    $movementData = new StockMovementData(
                        warehouseId: $lockedNote->warehouse_id,
                        catalogItemId: $catalogItem->id,
                        baseQuantity: (float) $line->base_quantity,
                        type: $movementType,
                        source: $lockedNote,
                        sourceNumber: $lockedNote->number,
                        presentationId: $presentation->id,
                        presentationQuantity: (float) $line->quantity,
                        reasonCode: $reason->code,
                        notes: $line->notes ?? $lockedNote->observation,
                        movedAt: $movedAt,
                    );

                    $movement = $this->stockLedgerService->out($movementData);

                    $line->update([
                        'base_unit_cost' => $movement->unit_cost,
                        'total_cost' => $movement->total_cost,
                    ]);
                } elseif ($lockedNote->type === WarehouseNoteType::Transfer) {
                    if (empty($lockedNote->target_warehouse_id)) {
                        throw new InventoryRuleException('El traslado no cuenta con almacén de destino.', 'target_warehouse_id');
                    }

                    $movementData = new StockMovementData(
                        warehouseId: $lockedNote->warehouse_id,
                        catalogItemId: $catalogItem->id,
                        baseQuantity: (float) $line->base_quantity,
                        type: MovementType::TransferOut,
                        source: $lockedNote,
                        sourceNumber: $lockedNote->number,
                        presentationId: $presentation->id,
                        presentationQuantity: (float) $line->quantity,
                        reasonCode: $reason->code,
                        notes: $line->notes ?? $lockedNote->observation,
                        movedAt: $movedAt,
                    );

                    [$outMovement] = $this->stockLedgerService->transfer($movementData, $lockedNote->target_warehouse_id);

                    $line->update([
                        'base_unit_cost' => $outMovement->unit_cost,
                        'total_cost' => $outMovement->total_cost,
                    ]);
                }
            }

            $totalCost = (float) WarehouseNoteItem::where('warehouse_note_id', $lockedNote->id)->sum('total_cost');

            $lockedNote->update([
                'status' => WarehouseNoteStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'total_cost' => round($totalCost, 4),
            ]);

            return $lockedNote->fresh(['warehouse', 'targetWarehouse', 'reason', 'creator', 'approver', 'items.catalogItem.baseUnit', 'items.presentation']) ?? $lockedNote;
        });
    }

    /**
     * Anula una nota de almacén. Si ya estaba aprobada, revierte cada movimiento de kardex en orden inverso.
     */
    public function cancel(WarehouseNote $note, string $reason, User $actor, bool $fromCount = false): WarehouseNote
    {
        return DB::transaction(function () use ($note, $reason, $actor, $fromCount): WarehouseNote {
            /** @var WarehouseNote $lockedNote */
            $lockedNote = WarehouseNote::where('id', $note->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedNote->isCancelled()) {
                throw new InventoryRuleException('La nota de almacén ya ha sido anulada previamente.', 'status');
            }

            if ($lockedNote->inventory_count_id !== null && ! $fromCount) {
                throw new InventoryRuleException('Las notas generadas por toma de inventario solo pueden anularse anulando la toma correspondiente.', 'status');
            }

            if ($lockedNote->isApproved()) {
                $movements = $lockedNote->kardexMovements()
                    ->whereNull('reverses_id')
                    ->whereDoesntHave('reversedBy')
                    ->orderByDesc('created_at')
                    ->get();

                foreach ($movements as $movement) {
                    $this->stockLedgerService->reverse($movement, $lockedNote, $lockedNote->number);
                }
            }

            $lockedNote->update([
                'status' => WarehouseNoteStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancel_reason' => trim($reason),
            ]);

            return $lockedNote->fresh(['warehouse', 'targetWarehouse', 'reason', 'creator', 'canceller', 'items.catalogItem.baseUnit', 'items.presentation']) ?? $lockedNote;
        });
    }
}
