<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Enums\SystemReason;
use App\Modules\Inventory\Enums\WarehouseNoteStatus;
use App\Modules\Inventory\Enums\WarehouseNoteType;
use App\Modules\Inventory\Enums\WarehouseReasonType;
use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseNote;
use App\Modules\Inventory\Models\WarehouseReason;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class WarehouseNoteService
{
    /**
     * Registra una nueva nota de almacén en borrador (o la aprueba si approve=true y el motivo no requiere aprobación).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, Branch $activeBranch): WarehouseNote
    {
        return DB::transaction(function () use ($data, $actor, $activeBranch) {
            /** @var WarehouseNoteType $type */
            $type = $data['type'] instanceof WarehouseNoteType
                ? $data['type']
                : WarehouseNoteType::from((string) $data['type']);

            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::with('branch')->findOrFail($data['warehouse_id']);

            if ($warehouse->branch_id !== $activeBranch->id) {
                throw new InventoryRuleException('El almacén seleccionado no pertenece a la sede activa.', 'warehouse_id');
            }

            if (! $warehouse->is_active) {
                throw new InventoryRuleException('El almacén de origen no está activo.', 'warehouse_id');
            }

            /** @var WarehouseReason $reason */
            $reason = WarehouseReason::findOrFail($data['reason_id']);
            $this->validateReasonForType($reason, $type, ! empty($data['inventory_count_id']));

            /** @var Warehouse|null $targetWarehouse */
            $targetWarehouse = null;
            if ($type === WarehouseNoteType::Transfer) {
                if (empty($data['target_warehouse_id'])) {
                    throw new InventoryRuleException('Debe indicar el almacén de destino para traslados.', 'target_warehouse_id');
                }

                if ($data['target_warehouse_id'] === $warehouse->id) {
                    throw new InventoryRuleException('El almacén de destino no puede ser igual al de origen.', 'target_warehouse_id');
                }

                /** @var Warehouse $targetWarehouse */
                $targetWarehouse = Warehouse::findOrFail($data['target_warehouse_id']);
                if (! $targetWarehouse->is_active) {
                    throw new InventoryRuleException('El almacén de destino no está activo.', 'target_warehouse_id');
                }
            }

            /** @var list<array<string, mixed>> $rawItems */
            $rawItems = (array) ($data['items'] ?? []);
            if (empty($rawItems)) {
                throw new InventoryRuleException('La nota debe contener al menos una línea.', 'items');
            }

            $processedItems = $this->processItems($rawItems, $type, $warehouse);

            $number = SequentialCodeGenerator::next("warehouse_note_{$type->value}", $type->codePrefix());

            /** @var WarehouseNote $note */
            $note = WarehouseNote::create([
                'number' => $number,
                'type' => $type,
                'status' => WarehouseNoteStatus::Draft,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'target_warehouse_id' => $targetWarehouse?->id,
                'reason_id' => $reason->id,
                'inventory_count_id' => $data['inventory_count_id'] ?? null,
                'document_date' => $data['document_date'],
                'reference_document' => $data['reference_document'] ?? null,
                'observation' => $data['observation'] ?? null,
                'total_cost' => $processedItems['total_cost'],
                'created_by' => $actor->id,
            ]);

            foreach ($processedItems['lines'] as $line) {
                $note->items()->create($line);
            }

            // Aprobación en un paso si corresponde
            $shouldApprove = ! empty($data['approve']) && ! $reason->requires_approval;
            if ($shouldApprove) {
                $approvalService = app(WarehouseNoteApprovalService::class);
                $note = $approvalService->approve($note, $actor);
            }

            return $note->load(['warehouse', 'targetWarehouse', 'reason', 'creator', 'items.catalogItem.baseUnit', 'items.presentation']);
        });
    }

    /**
     * Modifica una nota de almacén existente en estado borrador.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(WarehouseNote $note, array $data, User $actor): WarehouseNote
    {
        if (! $note->isDraft()) {
            throw new InventoryRuleException('Solo se pueden modificar notas en borrador.', 'status');
        }

        return DB::transaction(function () use ($note, $data): WarehouseNote {
            /** @var WarehouseReason $reason */
            $reason = WarehouseReason::findOrFail($data['reason_id']);
            $this->validateReasonForType($reason, $note->type);

            /** @var Warehouse|null $targetWarehouse */
            $targetWarehouse = null;
            if ($note->type === WarehouseNoteType::Transfer) {
                $targetId = $data['target_warehouse_id'] ?? $note->target_warehouse_id;
                if (empty($targetId)) {
                    throw new InventoryRuleException('Debe indicar el almacén de destino para traslados.', 'target_warehouse_id');
                }

                if ($targetId === $note->warehouse_id) {
                    throw new InventoryRuleException('El almacén de destino no puede ser igual al de origen.', 'target_warehouse_id');
                }

                /** @var Warehouse $targetWarehouse */
                $targetWarehouse = Warehouse::findOrFail($targetId);
                if (! $targetWarehouse->is_active) {
                    throw new InventoryRuleException('El almacén de destino no está activo.', 'target_warehouse_id');
                }
            }

            /** @var list<array<string, mixed>> $rawItems */
            $rawItems = (array) ($data['items'] ?? []);
            if (empty($rawItems)) {
                throw new InventoryRuleException('La nota debe contener al menos una línea.', 'items');
            }

            $processedItems = $this->processItems($rawItems, $note->type, $note->warehouse);

            $note->update([
                'reason_id' => $reason->id,
                'target_warehouse_id' => $targetWarehouse?->id,
                'document_date' => $data['document_date'],
                'reference_document' => $data['reference_document'] ?? null,
                'observation' => $data['observation'] ?? null,
                'total_cost' => $processedItems['total_cost'],
            ]);

            $note->items()->delete();
            foreach ($processedItems['lines'] as $line) {
                $note->items()->create($line);
            }

            /** @var WarehouseNote $freshNote */
            $freshNote = $note->fresh(['warehouse', 'targetWarehouse', 'reason', 'creator', 'items.catalogItem.baseUnit', 'items.presentation']);

            return $freshNote;
        });
    }

    /**
     * Construye la consulta de notas con filtros y visibilidad por sede activa y traslados entrantes.
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $accessibleBranchIds
     * @return Builder<WarehouseNote>
     */
    public function query(array $filters, array $accessibleBranchIds, ?Branch $activeBranch): Builder
    {
        $query = WarehouseNote::query()
            ->with(['warehouse', 'targetWarehouse', 'reason', 'creator', 'approver', 'canceller']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (string) $filters['warehouse_id']);
        } elseif ($activeBranch !== null && in_array($activeBranch->id, $accessibleBranchIds, true)) {
            $activeBranchId = $activeBranch->id;
            $query->where(function (Builder $q) use ($activeBranchId) {
                $q->where('branch_id', $activeBranchId)
                    ->orWhereHas('targetWarehouse', function (Builder $tw) use ($activeBranchId) {
                        $tw->where('branch_id', $activeBranchId);
                    });
            });
        } else {
            $query->whereIn('branch_id', $accessibleBranchIds);
        }

        if (! empty($filters['type'])) {
            $query->where('type', (string) $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['reason_id'])) {
            $query->where('reason_id', (string) $filters['reason_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('document_date', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('document_date', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('number', 'ilike', "%{$search}%")
                    ->orWhere('reference_document', 'ilike', "%{$search}%")
                    ->orWhere('observation', 'ilike', "%{$search}%");
            });
        }

        return $query->orderByDesc('document_date')->orderByDesc('created_at');
    }

    /**
     * Valida que el motivo corresponda al tipo de nota y no sea un motivo reservado de sistema.
     */
    private function validateReasonForType(WarehouseReason $reason, WarehouseNoteType $type, bool $fromCount = false): void
    {
        $isTypeCompatible = match ($type) {
            WarehouseNoteType::In => in_array($reason->type, [WarehouseReasonType::In, WarehouseReasonType::Adjustment], true),
            WarehouseNoteType::Out => in_array($reason->type, [WarehouseReasonType::Out, WarehouseReasonType::Adjustment], true),
            WarehouseNoteType::Transfer => $reason->type === WarehouseReasonType::Transfer,
        };

        if (! $isTypeCompatible) {
            throw new InventoryRuleException("El motivo '{$reason->name}' no corresponde al tipo de nota {$type->label()}.", 'reason_id');
        }

        // Prohibir motivos de sistema salvo OPENING en notas de ingreso y TRANSFER en notas de traslado (o ajustes de conteo si proviene de toma)
        if ($reason->is_system) {
            $isOpeningIn = $type === WarehouseNoteType::In && $reason->code === SystemReason::Opening->value;
            $isTransfer = $type === WarehouseNoteType::Transfer && $reason->code === SystemReason::Transfer->value;
            $isCountAdjustment = $fromCount && in_array($reason->code, [SystemReason::CountAdjustmentIn->value, SystemReason::CountAdjustmentOut->value], true);

            if (! $isOpeningIn && ! $isTransfer && ! $isCountAdjustment) {
                throw new InventoryRuleException('Los motivos de sistema no pueden utilizarse en notas manuales de almacén.', 'reason_id');
            }
        }
    }

    /**
     * Procesa y valida las líneas de la nota de almacén.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{lines: list<array<string, mixed>>, total_cost: float}
     */
    private function processItems(array $items, WarehouseNoteType $type, Warehouse $warehouse): array
    {
        $lines = [];
        $totalCost = 0.0;
        $lineNumber = 1;

        foreach ($items as $itemData) {
            /** @var ItemPresentation $presentation */
            $presentation = ItemPresentation::with('catalogItem.baseUnit')->findOrFail($itemData['item_presentation_id']);
            /** @var CatalogItem|null $catalogItem */
            $catalogItem = $presentation->catalogItem;

            if ($catalogItem === null || ! $catalogItem->track_stock || ! $catalogItem->is_active) {
                throw new InventoryRuleException('El artículo seleccionado no registra stock o se encuentra inactivo.', 'items');
            }

            $qty = (float) $itemData['quantity'];
            $factor = (float) $presentation->factor;
            $baseQty = round($qty * $factor, 4);

            $baseUnit = $catalogItem->baseUnit;
            if (! $baseUnit->allow_decimals && fmod($baseQty, 1.0) !== 0.0) {
                throw new InventoryRuleException("La unidad base '{$baseUnit->name}' del artículo '{$catalogItem->name}' no admite decimales.", 'items');
            }

            if ($type === WarehouseNoteType::In) {
                $unitCost = (float) ($itemData['unit_cost'] ?? 0.0);
                if ($unitCost < 0) {
                    throw new InventoryRuleException('El costo unitario no puede ser negativo.', 'items');
                }
                $lineTotal = round($qty * $unitCost, 4);
            } else {
                // En salidas o traslados, el costo se estima con el promedio actual del almacén origen
                $currentAvgCost = (float) (Stock::where('warehouse_id', $warehouse->id)
                    ->where('catalog_item_id', $catalogItem->id)
                    ->value('avg_cost') ?? 0.0);
                $unitCost = round($currentAvgCost * $factor, 4);
                $lineTotal = round($baseQty * $currentAvgCost, 4);
            }

            $lines[] = [
                'line' => $lineNumber++,
                'catalog_item_id' => $catalogItem->id,
                'item_presentation_id' => $presentation->id,
                'quantity' => $qty,
                'base_quantity' => $baseQty,
                'unit_cost' => $unitCost,
                'base_unit_cost' => null,
                'total_cost' => $lineTotal,
                'notes' => ! empty($itemData['notes']) ? trim((string) $itemData['notes']) : null,
            ];

            $totalCost = round($totalCost + $lineTotal, 4);
        }

        return [
            'lines' => $lines,
            'total_cost' => $totalCost,
        ];
    }
}
