<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class StockLedgerService
{
    public function __construct(
        protected SystemParameterService $parameterService
    ) {}

    /**
     * Registra un movimiento de entrada al inventario.
     */
    public function in(StockMovementData $data): KardexMovement
    {
        if ($data->type->direction() !== MovementDirection::In) {
            throw new InvalidArgumentException("El tipo de movimiento {$data->type->value} no es una entrada.");
        }

        return $this->apply($data);
    }

    /**
     * Registra un movimiento de salida del inventario.
     */
    public function out(StockMovementData $data): KardexMovement
    {
        if ($data->type->direction() !== MovementDirection::Out) {
            throw new InvalidArgumentException("El tipo de movimiento {$data->type->value} no es una salida.");
        }

        return $this->apply($data);
    }

    /**
     * Registra un traslado entre dos almacenes enlazado por un transfer_group_id común.
     *
     * @return array{0: KardexMovement, 1: KardexMovement}
     */
    public function transfer(StockMovementData $out, string $toWarehouseId): array
    {
        $transferGroupId = (string) Str::uuid();

        // 1. Salida del almacén origen
        $outMovement = $this->apply($out, transferGroupId: $transferGroupId);

        // 2. Entrada en el almacén destino con el mismo costo unitario aplicado
        $inData = new StockMovementData(
            warehouseId: $toWarehouseId,
            catalogItemId: $out->catalogItemId,
            baseQuantity: $out->baseQuantity,
            type: MovementType::TransferIn,
            source: $out->source,
            sourceNumber: $out->sourceNumber,
            unitCost: $outMovement->unit_cost,
            presentationId: $out->presentationId,
            presentationQuantity: $out->presentationQuantity,
            reasonCode: $out->reasonCode,
            notes: $out->notes,
            movedAt: $out->movedAt,
        );

        $inMovement = $this->apply($inData, transferGroupId: $transferGroupId);

        return [$outMovement, $inMovement];
    }

    /**
     * Revierte un movimiento de kardex creando el movimiento opuesto al mismo costo unitario.
     */
    public function reverse(KardexMovement $movement, Model $source, string $sourceNumber): KardexMovement
    {
        if ($movement->reversedBy()->exists()) {
            throw new LogicException('El movimiento ya ha sido revertido previamente.');
        }

        $reversalType = $movement->direction === MovementDirection::In
            ? MovementType::ReversalOut
            : MovementType::ReversalIn;

        /** @var WarehouseReason|null $reason */
        $reason = $movement->reason;

        $data = new StockMovementData(
            warehouseId: $movement->warehouse_id,
            catalogItemId: $movement->catalog_item_id,
            baseQuantity: $movement->quantity,
            type: $reversalType,
            source: $source,
            sourceNumber: $sourceNumber,
            unitCost: $movement->unit_cost,
            presentationId: $movement->item_presentation_id,
            presentationQuantity: $movement->presentation_quantity,
            reasonCode: $reason?->code,
            notes: "Reversión de movimiento {$movement->id}",
        );

        return $this->apply($data, reversesId: $movement->id);
    }

    /**
     * Aplica un lote de movimientos ordenados por almacén e ítem para prevenir deadlocks.
     *
     * @param  list<StockMovementData>  $movements
     * @return list<KardexMovement>
     */
    public function applyBatch(array $movements): array
    {
        // Orden determinista por warehouseId y catalogItemId
        usort($movements, function (StockMovementData $a, StockMovementData $b) {
            $cmp = strcmp($a->warehouseId, $b->warehouseId);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a->catalogItemId, $b->catalogItemId);
        });

        $results = [];
        foreach ($movements as $movement) {
            $results[] = $this->apply($movement);
        }

        return $results;
    }

    /**
     * Aplica internamente el movimiento de inventario sobre la fila de stock y crea el registro de kardex.
     */
    protected function apply(
        StockMovementData $data,
        ?string $transferGroupId = null,
        ?string $reversesId = null
    ): KardexMovement {
        if (DB::transactionLevel() <= 0) {
            throw new LogicException('StockLedgerService requiere una transacción de base de datos activa.');
        }

        if ($data->baseQuantity <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        /** @var CatalogItem $catalogItem */
        $catalogItem = CatalogItem::findOrFail($data->catalogItemId);

        if (! $catalogItem->track_stock) {
            throw new InventoryRuleException("El ítem '{$catalogItem->name}' no registra stock.", 'track_stock');
        }

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::findOrFail($data->warehouseId);

        // Bloqueo pesimista ordenado de la fila de stock
        Stock::firstOrCreate(
            ['warehouse_id' => $data->warehouseId, 'catalog_item_id' => $data->catalogItemId],
            ['quantity' => 0, 'avg_cost' => 0, 'min_stock' => 0]
        );

        /** @var Stock $stock */
        $stock = Stock::where('warehouse_id', $data->warehouseId)
            ->where('catalog_item_id', $data->catalogItemId)
            ->lockForUpdate()
            ->firstOrFail();

        $q0 = (string) $stock->quantity;
        $c0 = (string) $stock->avg_cost;
        $q = (string) $data->baseQuantity;

        if ($data->type->direction() === MovementDirection::In) {
            $unitCostApplied = round((float) ($data->unitCost ?? 0), 6);
            $c = (string) $unitCostApplied;
            $newQty = bcadd($q0, $q, 4);

            if (bccomp($q0, '0', 4) > 0) {
                $totalOld = bcmul($q0, $c0, 8);
                $totalNew = bcmul($q, $c, 8);
                $sumTotal = bcadd($totalOld, $totalNew, 8);
                $newAvg = bcdiv($sumTotal, $newQty, 8);
            } else {
                $newAvg = $c;
            }

            $newAvg = round((float) $newAvg, 6);
        } else {
            $newQty = bcsub($q0, $q, 4);

            if (bccomp($newQty, '0', 4) < 0) {
                $allowNegative = $this->parameterService->boolean('inventory.allow_negative_stock');
                if (! $allowNegative) {
                    throw new InsufficientStockException(
                        "Stock insuficiente para '{$catalogItem->name}' en el almacén '{$warehouse->name}': disponible {$q0}, requerido {$q}.",
                        [
                            'catalog_item_id' => $catalogItem->id,
                            'warehouse_id' => $warehouse->id,
                            'available' => (float) $q0,
                            'required' => (float) $q,
                        ]
                    );
                }
            }

            if ($data->type === MovementType::ReversalOut && $data->unitCost !== null) {
                $unitCostApplied = round((float) $data->unitCost, 6);
                if (bccomp($newQty, '0', 4) > 0) {
                    $totalOld = bcmul($q0, $c0, 8);
                    $totalRemoved = bcmul($q, (string) $unitCostApplied, 8);
                    $newTotal = bcsub($totalOld, $totalRemoved, 8);
                    $newAvg = bccomp($newTotal, '0', 8) > 0 ? round((float) bcdiv($newTotal, $newQty, 8), 6) : 0.0;
                } else {
                    $newAvg = 0.0;
                }
            } else {
                $unitCostApplied = round((float) $c0, 6);
                $newAvg = round((float) $c0, 6);
            }
        }

        $timestamp = $data->movedAt ?? now();

        $stock->forceFill([
            'quantity' => (float) $newQty,
            'avg_cost' => $newAvg,
            'last_movement_at' => $timestamp,
        ])->save();

        $reasonId = null;
        if ($data->reasonCode !== null) {
            $reasonId = WarehouseReason::where('code', $data->reasonCode)->value('id');
        }

        $totalCost = round((float) bcmul((string) $data->baseQuantity, (string) $unitCostApplied, 8), 4);
        $balanceQuantity = (float) $newQty;
        $balanceUnitCost = $newAvg;
        $balanceTotalCost = round((float) bcmul((string) $newQty, (string) $newAvg, 8), 4);

        /** @var KardexMovement $movement */
        $movement = KardexMovement::create([
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $data->warehouseId,
            'catalog_item_id' => $data->catalogItemId,
            'item_presentation_id' => $data->presentationId,
            'reason_id' => $reasonId,
            'direction' => $data->type->direction(),
            'movement_type' => $data->type,
            'quantity' => $data->baseQuantity,
            'presentation_quantity' => $data->presentationQuantity,
            'unit_cost' => $unitCostApplied,
            'total_cost' => $totalCost,
            'balance_quantity' => $balanceQuantity,
            'balance_unit_cost' => $balanceUnitCost,
            'balance_total_cost' => $balanceTotalCost,
            'source_type' => $data->source->getMorphClass(),
            'source_id' => (string) $data->source->getKey(),
            'source_number' => $data->sourceNumber,
            'transfer_group_id' => $transferGroupId,
            'reverses_id' => $reversesId,
            'user_id' => auth()->id(),
            'notes' => $data->notes,
            'moved_at' => $timestamp,
            'created_at' => $timestamp,
        ]);

        return $movement;
    }
}
