<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\SystemReason;
use App\Modules\Inventory\Enums\WarehouseNoteStatus;
use App\Modules\Inventory\Enums\WarehouseNoteType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseNote;
use App\Modules\Inventory\Models\WarehouseNoteItem;
use App\Modules\Inventory\Models\WarehouseReason;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Purchases\Models\PurchaseReceipt;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos de muestra de Stock, Kardex y Notas de Almacén (solo local/testing).
 * Depende de CatalogSeeder, InventoryMastersSeeder, BranchSeeder,
 * PurchasesDemoSeeder y PurchasesReceiptsDemoSeeder.
 * Es idempotente: si las notas demo ya existen, solo actualiza límites de stock sin duplicar kardex.
 */
class StockDemoSeeder extends Seeder
{
    /**
     * @var array<string, array{opening: float, cost: float, min: int, max: int}>
     */
    private const INITIAL_STOCK = [
        'ITM-000001' => ['opening' => 10.0, 'cost' => 2850.00, 'min' => 5, 'max' => 25],   // Laptop Lenovo
        'ITM-000002' => ['opening' => 15.0, 'cost' => 820.00,  'min' => 8, 'max' => 30],   // Monitor Samsung
        'ITM-000003' => ['opening' => 30.0, 'cost' => 160.00,  'min' => 10, 'max' => 50],  // Teclado Mecánico
        'ITM-000004' => ['opening' => 40.0, 'cost' => 65.00,   'min' => 15, 'max' => 60],  // Mouse Óptico
        'ITM-000005' => ['opening' => 25.0, 'cost' => 230.00,  'min' => 10, 'max' => 50],  // SSD 1TB
        'ITM-000006' => ['opening' => 20.0, 'cost' => 150.00,  'min' => 8, 'max' => 40],   // RAM 16GB
        'ITM-000007' => ['opening' => 8.0,  'cost' => 620.00,  'min' => 3, 'max' => 15],   // Impresora Epson
        'ITM-000008' => ['opening' => 100.0, 'cost' => 11.50,   'min' => 20, 'max' => 150], // Papel Fotocopia A4
        'ITM-000009' => ['opening' => 50.0, 'cost' => 18.00,   'min' => 15, 'max' => 80],  // Cable HDMI
        'ITM-000010' => ['opening' => 300.0, 'cost' => 3.10,    'min' => 50, 'max' => 500], // Arroz Extra 1kg
        'ITM-000011' => ['opening' => 120.0, 'cost' => 6.20,    'min' => 24, 'max' => 200], // Aceite Vegetal 1L
        'ITM-000012' => ['opening' => 240.0, 'cost' => 3.20,    'min' => 48, 'max' => 300], // Leche Evaporada
        'ITM-000013' => ['opening' => 50.0, 'cost' => 14.00,   'min' => 10, 'max' => 80],  // Detergente 2kg
        'ITM-000014' => ['opening' => 100.0, 'cost' => 4.50,    'min' => 24, 'max' => 150], // Pasta Dental
        'ITM-000015' => ['opening' => 200.0, 'cost' => 0.50,    'min' => 50, 'max' => 300], // Lapicero Azul
        'ITM-000016' => ['opening' => 2.0,  'cost' => 280.00,  'min' => 5, 'max' => 20],   // Taladro (bajo mínimo deliberado)
        'ITM-000017' => ['opening' => 20.0, 'cost' => 60.00,   'min' => 6, 'max' => 40],   // Aceite Motor Galón
    ];

    public function run(): void
    {
        $mainWarehouse = Warehouse::where('code', 'ALM01')->first()
            ?? Warehouse::where('is_default', true)->first();
        $storeWarehouse = Warehouse::where('code', 'ALM02')->first();

        if ($mainWarehouse === null) {
            return;
        }

        // Si ya existen notas demo o movimientos vinculados, refrescar límites y salir
        if (WarehouseNote::where('number', 'like', 'NI-%')->exists()
            || KardexMovement::where('source_number', 'like', 'NI-%')->exists()
            || KardexMovement::where('source_number', 'like', 'DEMO-%')->exists()) {
            $this->seedStockLimits($mainWarehouse, $storeWarehouse);

            return;
        }

        $items = CatalogItem::with('defaultPresentation')
            ->where('track_stock', true)
            ->get()
            ->keyBy('code');

        if ($items->isEmpty()) {
            return;
        }

        $admin = User::where('email', 'admin@example.com')->first();
        if ($admin !== null && ! auth()->check()) {
            auth()->login($admin);
        }

        /** @var StockLedgerService $ledger */
        $ledger = app(StockLedgerService::class);

        // Cronograma relativo de fechas estrictamente ascendentes dentro del mes
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $totalMinutes = max(60, (int) $startOfMonth->diffInMinutes($now));
        $interval = max(5, (int) floor($totalMinutes / 5));

        $openingDate = $startOfMonth->copy()->subDay()->setTime(18, 0);
        $transferDate = $startOfMonth->copy()->addMinutes($interval * 3);
        $internalDate = $startOfMonth->copy()->addMinutes($interval * 4);

        DB::transaction(function () use (
            $ledger,
            $items,
            $mainWarehouse,
            $storeWarehouse,
            $admin,
            $openingDate,
            $transferDate,
            $internalDate
        ) {
            // 1. Nota de Ingreso por Inventario Inicial de apertura (NI-000001)
            $this->seedOpeningNote($ledger, $items, $mainWarehouse, $admin, $openingDate);

            // 2. Movimientos de compra vinculados a las recepciones RC-000001 y RC-000002
            //    (PurchaseReceipt como source; cada movimiento usa la fecha de
            //    confirmación de SU PROPIA recepción, no una fecha compartida)
            $this->seedPurchaseStock($ledger);

            // 3. Nota de Traslado hacia Almacén de Tienda (TR-000001)
            if ($storeWarehouse !== null) {
                $this->seedTransferNote($ledger, $items, $mainWarehouse, $storeWarehouse, $admin, $transferDate);
            }

            // 4. Notas de Salida por consumo interno y merma (NS-000001 y NS-000002)
            $this->seedInternalNotes($ledger, $items, $mainWarehouse, $admin, $internalDate);

            // 5. Notas adicionales para enriquecer la UI (Borradores y Anulada)
            $this->seedUiSampleNotes($items, $mainWarehouse, $admin);

            // 6. Configurar límites min_stock y max_stock
            $this->seedStockLimits($mainWarehouse, $storeWarehouse);
        });
    }

    /**
     * Registra la Nota de Ingreso por Inventario Inicial de apertura (NI-000001)
     * y genera sus movimientos correspondientes en el Kardex.
     *
     * @param  Collection<string, CatalogItem>  $items
     */
    private function seedOpeningNote(
        StockLedgerService $ledger,
        Collection $items,
        Warehouse $warehouse,
        ?User $actor,
        Carbon $date
    ): void {
        $reason = WarehouseReason::where('code', SystemReason::Opening->value)->first();
        if ($reason === null) {
            return;
        }

        $note = WarehouseNote::create([
            'number' => 'NI-000001',
            'type' => WarehouseNoteType::In,
            'status' => WarehouseNoteStatus::Approved,
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'reason_id' => $reason->id,
            'document_date' => $date->toDateString(),
            'reference_document' => 'INV-INICIAL-2026',
            'observation' => 'Inventario inicial de apertura de gestión',
            'total_cost' => 0.0,
            'created_by' => $actor?->id,
            'approved_by' => $actor?->id,
            'approved_at' => $date,
        ]);
        SequentialCodeGenerator::reserve('warehouse_note_in', $note->number);

        $lineIndex = 1;
        $totalCost = 0.0;

        foreach (self::INITIAL_STOCK as $code => $config) {
            $item = $items->get($code);
            if ($item === null) {
                continue;
            }

            $pres = $item->defaultPresentation;
            $lineTotal = round($config['opening'] * $config['cost'], 4);
            $totalCost += $lineTotal;

            WarehouseNoteItem::create([
                'warehouse_note_id' => $note->id,
                'line' => $lineIndex++,
                'catalog_item_id' => $item->id,
                'item_presentation_id' => $pres?->id,
                'quantity' => $config['opening'],
                'base_quantity' => $config['opening'],
                'unit_cost' => $config['cost'],
                'base_unit_cost' => $config['cost'],
                'total_cost' => $lineTotal,
                'notes' => 'Saldo inicial',
            ]);

            $ledger->in(new StockMovementData(
                warehouseId: $warehouse->id,
                catalogItemId: $item->id,
                baseQuantity: $config['opening'],
                type: MovementType::Opening,
                source: $note,
                sourceNumber: $note->number,
                unitCost: $config['cost'],
                presentationId: $pres?->id,
                presentationQuantity: $config['opening'],
                reasonCode: SystemReason::Opening->value,
                notes: 'Inventario inicial de apertura de gestión',
                movedAt: $date,
            ));
        }

        $note->update(['total_cost' => round($totalCost, 4)]);
    }

    /**
     * Registra movimientos de compra en el kardex usando las PurchaseReceipt
     * (RC-000001 y RC-000002) como source. Éstas deben existir antes de llamar
     * a este método (creadas por PurchasesReceiptsDemoSeeder).
     */
    private function seedPurchaseStock(StockLedgerService $ledger): void
    {
        $receipts = PurchaseReceipt::where('number', 'like', 'RC-%')
            ->where('status', 'confirmed')
            ->with(['items' => fn ($q) => $q->with(['catalogItem', 'presentation'])])
            ->get();

        foreach ($receipts as $receipt) {
            $movedAt = $receipt->confirmed_at ?? $receipt->receipt_date;

            foreach ($receipt->items as $item) {
                if ($item->is_service || $item->catalog_item_id === null) {
                    continue;
                }

                $catalogItem = $item->catalogItem;
                if ($catalogItem === null || ! $catalogItem->track_stock) {
                    continue;
                }

                $ledger->in(new StockMovementData(
                    warehouseId: $receipt->warehouse_id,
                    catalogItemId: $item->catalog_item_id,
                    baseQuantity: (float) ($item->base_quantity ?? $item->quantity),
                    type: MovementType::Purchase,
                    source: $receipt,
                    sourceNumber: $receipt->number,
                    unitCost: $item->base_unit_cost !== null ? (float) $item->base_unit_cost : (float) $item->unit_cost,
                    presentationId: $item->item_presentation_id,
                    presentationQuantity: (float) $item->quantity,
                    reasonCode: SystemReason::Purchase->value,
                    notes: "Recepción de compra {$receipt->number}",
                    movedAt: $movedAt,
                ));
            }
        }
    }

    /**
     * Registra la Nota de Traslado entre Almacén Principal y Tienda (TR-000001).
     *
     * @param  Collection<string, CatalogItem>  $items
     */
    private function seedTransferNote(
        StockLedgerService $ledger,
        Collection $items,
        Warehouse $fromWarehouse,
        Warehouse $toWarehouse,
        ?User $actor,
        Carbon $date
    ): void {
        $reason = WarehouseReason::where('code', SystemReason::Transfer->value)->first();
        if ($reason === null) {
            return;
        }

        $note = WarehouseNote::create([
            'number' => 'TR-000001',
            'type' => WarehouseNoteType::Transfer,
            'status' => WarehouseNoteStatus::Approved,
            'branch_id' => $fromWarehouse->branch_id,
            'warehouse_id' => $fromWarehouse->id,
            'target_warehouse_id' => $toWarehouse->id,
            'reason_id' => $reason->id,
            'document_date' => $date->toDateString(),
            'reference_document' => 'GUIA T001-00012',
            'observation' => 'Traslado de stock a almacén de tienda / exhibición',
            'total_cost' => 0.0,
            'created_by' => $actor?->id,
            'approved_by' => $actor?->id,
            'approved_at' => $date,
        ]);
        SequentialCodeGenerator::reserve('warehouse_note_transfer', $note->number);

        $transfers = [
            'ITM-000001' => 3.0,  // 3 Laptops a tienda
            'ITM-000002' => 5.0,  // 5 Monitores a tienda
            'ITM-000003' => 10.0, // 10 Teclados a tienda
            'ITM-000004' => 12.0, // 12 Mouses a tienda
            'ITM-000005' => 8.0,  // 8 SSDs a tienda
            'ITM-000008' => 15.0, // 15 Paquetes de papel a tienda
        ];

        $lineIndex = 1;
        $totalCost = 0.0;

        foreach ($transfers as $code => $qty) {
            $item = $items->get($code);
            if ($item === null) {
                continue;
            }

            $pres = $item->defaultPresentation;

            $outData = new StockMovementData(
                warehouseId: $fromWarehouse->id,
                catalogItemId: $item->id,
                baseQuantity: $qty,
                type: MovementType::TransferOut,
                source: $note,
                sourceNumber: $note->number,
                presentationId: $pres?->id,
                presentationQuantity: $qty,
                reasonCode: SystemReason::Transfer->value,
                notes: 'Traslado de stock a almacén de tienda / exhibición',
                movedAt: $date,
            );

            [$outMovement] = $ledger->transfer($outData, $toWarehouse->id);

            $lineTotal = (float) $outMovement->total_cost;
            $totalCost += $lineTotal;

            WarehouseNoteItem::create([
                'warehouse_note_id' => $note->id,
                'line' => $lineIndex++,
                'catalog_item_id' => $item->id,
                'item_presentation_id' => $pres?->id,
                'quantity' => $qty,
                'base_quantity' => $qty,
                'unit_cost' => (float) $outMovement->unit_cost,
                'base_unit_cost' => (float) $outMovement->unit_cost,
                'total_cost' => $lineTotal,
                'notes' => 'Traslado a tienda',
            ]);
        }

        $note->update(['total_cost' => round($totalCost, 4)]);
    }

    /**
     * Registra notas de salida por consumo interno (NS-000001) y producto dañado/merma (NS-000002).
     *
     * @param  Collection<string, CatalogItem>  $items
     */
    private function seedInternalNotes(
        StockLedgerService $ledger,
        Collection $items,
        Warehouse $warehouse,
        ?User $actor,
        Carbon $date
    ): void {
        $internalReason = WarehouseReason::where('code', 'INTERNAL_USE')->first();
        $damagedReason = WarehouseReason::where('code', 'DAMAGED')->first();
        // NS-000002 es un evento distinto y no relacionado con NS-000001 (merma al
        // descargar mercadería vs. consumo interno de oficina); no deben compartir fecha.
        $damagedDate = $date->copy()->addDays(2)->setTime(11, 15);

        // NS-000001: Consumo interno (Papel y Mouse)
        if ($internalReason !== null) {
            $note1 = WarehouseNote::create([
                'number' => 'NS-000001',
                'type' => WarehouseNoteType::Out,
                'status' => WarehouseNoteStatus::Approved,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'reason_id' => $internalReason->id,
                'document_date' => $date->toDateString(),
                'reference_document' => 'REQ-INT-001',
                'observation' => 'Salida por consumo interno de materiales de administración y operaciones',
                'total_cost' => 0.0,
                'created_by' => $actor?->id,
                'approved_by' => $actor?->id,
                'approved_at' => $date,
            ]);
            SequentialCodeGenerator::reserve('warehouse_note_out', $note1->number);

            $internalLines = [
                ['code' => 'ITM-000008', 'qty' => 2.0, 'note' => 'Consumo interno de papel en administración'],
                ['code' => 'ITM-000004', 'qty' => 1.0, 'note' => 'Mouse asignado a gerencia de operaciones'],
            ];

            $totalCost1 = 0.0;
            $lineIndex = 1;
            foreach ($internalLines as $line) {
                $item = $items->get($line['code']);
                if ($item === null) {
                    continue;
                }

                $pres = $item->defaultPresentation;
                $movement = $ledger->out(new StockMovementData(
                    warehouseId: $warehouse->id,
                    catalogItemId: $item->id,
                    baseQuantity: $line['qty'],
                    type: MovementType::NoteOut,
                    source: $note1,
                    sourceNumber: $note1->number,
                    presentationId: $pres?->id,
                    presentationQuantity: $line['qty'],
                    reasonCode: 'INTERNAL_USE',
                    notes: $line['note'],
                    movedAt: $date,
                ));

                $lineTotal = (float) $movement->total_cost;
                $totalCost1 += $lineTotal;

                WarehouseNoteItem::create([
                    'warehouse_note_id' => $note1->id,
                    'line' => $lineIndex++,
                    'catalog_item_id' => $item->id,
                    'item_presentation_id' => $pres?->id,
                    'quantity' => $line['qty'],
                    'base_quantity' => $line['qty'],
                    'unit_cost' => (float) $movement->unit_cost,
                    'base_unit_cost' => (float) $movement->unit_cost,
                    'total_cost' => $lineTotal,
                    'notes' => $line['note'],
                ]);
            }

            $note1->update(['total_cost' => round($totalCost1, 4)]);
        }

        // NS-000002: Producto dañado / merma (Leche)
        if ($damagedReason !== null) {
            $note2 = WarehouseNote::create([
                'number' => 'NS-000002',
                'type' => WarehouseNoteType::Out,
                'status' => WarehouseNoteStatus::Approved,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'reason_id' => $damagedReason->id,
                'document_date' => $damagedDate->toDateString(),
                'reference_document' => 'INF-MERMA-001',
                'observation' => 'Latas de leche dañadas durante la descarga de mercadería',
                'total_cost' => 0.0,
                'created_by' => $actor?->id,
                'approved_by' => $actor?->id,
                'approved_at' => $damagedDate,
            ]);
            SequentialCodeGenerator::reserve('warehouse_note_out', $note2->number);

            $damagedItem = $items->get('ITM-000012');
            if ($damagedItem !== null) {
                $pres = $damagedItem->defaultPresentation;
                $movement = $ledger->out(new StockMovementData(
                    warehouseId: $warehouse->id,
                    catalogItemId: $damagedItem->id,
                    baseQuantity: 2.0,
                    type: MovementType::NoteOut,
                    source: $note2,
                    sourceNumber: $note2->number,
                    presentationId: $pres?->id,
                    presentationQuantity: 2.0,
                    reasonCode: 'DAMAGED',
                    notes: 'Latas de leche dañadas durante descarga',
                    movedAt: $damagedDate,
                ));

                $lineTotal = (float) $movement->total_cost;

                WarehouseNoteItem::create([
                    'warehouse_note_id' => $note2->id,
                    'line' => 1,
                    'catalog_item_id' => $damagedItem->id,
                    'item_presentation_id' => $pres?->id,
                    'quantity' => 2.0,
                    'base_quantity' => 2.0,
                    'unit_cost' => (float) $movement->unit_cost,
                    'base_unit_cost' => (float) $movement->unit_cost,
                    'total_cost' => $lineTotal,
                    'notes' => 'Latas con abolladura',
                ]);

                $note2->update(['total_cost' => round($lineTotal, 4)]);
            }
        }
    }

    /**
     * Crea notas de almacén adicionales en Borrador y Anulada para enriquecer la experiencia en UI.
     *
     * @param  Collection<string, CatalogItem>  $items
     */
    private function seedUiSampleNotes(
        Collection $items,
        Warehouse $warehouse,
        ?User $actor
    ): void {
        $supplierBonusReason = WarehouseReason::where('code', 'SUPPLIER_BONUS')->first();
        $sampleGiftReason = WarehouseReason::where('code', 'SAMPLE_GIFT')->first();
        $shrinkageReason = WarehouseReason::where('code', 'SHRINKAGE')->first();

        // NI-000003: Borrador de ingreso por bonificación de proveedor
        if ($supplierBonusReason !== null) {
            $draftIn = WarehouseNote::create([
                'number' => 'NI-000003',
                'type' => WarehouseNoteType::In,
                'status' => WarehouseNoteStatus::Draft,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'reason_id' => $supplierBonusReason->id,
                'document_date' => Carbon::now()->toDateString(),
                'reference_document' => 'GUIA-BONIF-019',
                'observation' => 'Bonificación de teclados por meta de compras trimestral',
                'total_cost' => 320.00,
                'created_by' => $actor?->id,
            ]);
            SequentialCodeGenerator::reserve('warehouse_note_in', $draftIn->number);

            $kb = $items->get('ITM-000003');
            if ($kb !== null) {
                WarehouseNoteItem::create([
                    'warehouse_note_id' => $draftIn->id,
                    'line' => 1,
                    'catalog_item_id' => $kb->id,
                    'item_presentation_id' => $kb->defaultPresentation?->id,
                    'quantity' => 2.0,
                    'base_quantity' => 2.0,
                    'unit_cost' => 160.00,
                    'base_unit_cost' => 160.00,
                    'total_cost' => 320.00,
                    'notes' => 'Teclados bonificados',
                ]);
            }
        }

        // NS-000003: Borrador de salida por muestra comercial
        if ($sampleGiftReason !== null) {
            $draftOut = WarehouseNote::create([
                'number' => 'NS-000003',
                'type' => WarehouseNoteType::Out,
                'status' => WarehouseNoteStatus::Draft,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'reason_id' => $sampleGiftReason->id,
                'document_date' => Carbon::now()->toDateString(),
                'reference_document' => 'SOL-MUESTRA-002',
                'observation' => 'Entrega de muestra para demostración a cliente institucional',
                'total_cost' => 65.00,
                'created_by' => $actor?->id,
            ]);
            SequentialCodeGenerator::reserve('warehouse_note_out', $draftOut->number);

            $mouse = $items->get('ITM-000004');
            if ($mouse !== null) {
                WarehouseNoteItem::create([
                    'warehouse_note_id' => $draftOut->id,
                    'line' => 1,
                    'catalog_item_id' => $mouse->id,
                    'item_presentation_id' => $mouse->defaultPresentation?->id,
                    'quantity' => 1.0,
                    'base_quantity' => 1.0,
                    'unit_cost' => 65.00,
                    'base_unit_cost' => 65.00,
                    'total_cost' => 65.00,
                    'notes' => 'Muestra técnica',
                ]);
            }
        }

        // NS-000004: Salida anulada con motivo
        if ($shrinkageReason !== null) {
            $cancelledNote = WarehouseNote::create([
                'number' => 'NS-000004',
                'type' => WarehouseNoteType::Out,
                'status' => WarehouseNoteStatus::Cancelled,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'reason_id' => $shrinkageReason->id,
                'document_date' => Carbon::now()->subDays(1)->toDateString(),
                'reference_document' => 'INF-MERMA-DUP',
                'observation' => 'Salida errónea por merma de suministros',
                'total_cost' => 11.50,
                'created_by' => $actor?->id,
                'cancelled_by' => $actor?->id,
                'cancelled_at' => Carbon::now()->subHours(4),
                'cancel_reason' => 'Registro duplicado por digitación errónea del operador',
            ]);
            SequentialCodeGenerator::reserve('warehouse_note_out', $cancelledNote->number);

            $paper = $items->get('ITM-000008');
            if ($paper !== null) {
                WarehouseNoteItem::create([
                    'warehouse_note_id' => $cancelledNote->id,
                    'line' => 1,
                    'catalog_item_id' => $paper->id,
                    'item_presentation_id' => $paper->defaultPresentation?->id,
                    'quantity' => 1.0,
                    'base_quantity' => 1.0,
                    'unit_cost' => 11.50,
                    'base_unit_cost' => 11.50,
                    'total_cost' => 11.50,
                    'notes' => 'Línea de nota cancelada',
                ]);
            }
        }
    }

    /**
     * Establece los límites mínimo y máximo en las filas de stock.
     */
    private function seedStockLimits(Warehouse $mainWarehouse, ?Warehouse $storeWarehouse): void
    {
        $items = CatalogItem::where('track_stock', true)->get()->keyBy('code');

        foreach (self::INITIAL_STOCK as $code => $config) {
            $item = $items->get($code);
            if ($item === null) {
                continue;
            }

            Stock::where('warehouse_id', $mainWarehouse->id)
                ->where('catalog_item_id', $item->id)
                ->update([
                    'min_stock' => $config['min'],
                    'max_stock' => $config['max'],
                ]);

            if ($storeWarehouse !== null) {
                Stock::where('warehouse_id', $storeWarehouse->id)
                    ->where('catalog_item_id', $item->id)
                    ->update([
                        'min_stock' => max(1, (int) round($config['min'] / 2)),
                        'max_stock' => max(2, (int) round($config['max'] / 2)),
                    ]);
            }
        }
    }
}
