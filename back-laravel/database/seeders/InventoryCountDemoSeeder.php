<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\InventoryCountStatus;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\SystemReason;
use App\Modules\Inventory\Enums\WarehouseNoteStatus;
use App\Modules\Inventory\Enums\WarehouseNoteType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\InventoryCount;
use App\Modules\Inventory\Models\InventoryCountItem;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseNote;
use App\Modules\Inventory\Models\WarehouseNoteItem;
use App\Modules\Inventory\Models\WarehouseReason;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryCountDemoSeeder extends Seeder
{
    /**
     * @var array<string, array{opening: float, cost: float}>
     */
    private const INITIAL_STOCK = [
        'ITM-000001' => ['opening' => 10.0, 'cost' => 2850.00], // Laptop Lenovo
        'ITM-000002' => ['opening' => 15.0, 'cost' => 820.00],  // Monitor Samsung
        'ITM-000003' => ['opening' => 30.0, 'cost' => 160.00],  // Teclado Mecánico
        'ITM-000004' => ['opening' => 40.0, 'cost' => 65.00],   // Mouse Óptico
        'ITM-000005' => ['opening' => 25.0, 'cost' => 230.00],  // SSD 1TB
        'ITM-000006' => ['opening' => 20.0, 'cost' => 150.00],  // RAM 16GB
        'ITM-000007' => ['opening' => 8.0,  'cost' => 620.00],  // Impresora Epson
        'ITM-000008' => ['opening' => 100.0, 'cost' => 11.50],  // Papel Fotocopia A4
        'ITM-000009' => ['opening' => 50.0, 'cost' => 18.00],   // Cable HDMI
        'ITM-000010' => ['opening' => 300.0, 'cost' => 3.10],   // Arroz Extra 1kg
        'ITM-000011' => ['opening' => 120.0, 'cost' => 6.20],   // Aceite Vegetal 1L
        'ITM-000012' => ['opening' => 240.0, 'cost' => 3.20],   // Leche Evaporada
        'ITM-000013' => ['opening' => 50.0, 'cost' => 14.00],   // Detergente 2kg
        'ITM-000014' => ['opening' => 100.0, 'cost' => 4.50],   // Pasta Dental
        'ITM-000015' => ['opening' => 200.0, 'cost' => 0.50],   // Lapicero Azul
        'ITM-000016' => ['opening' => 2.0,  'cost' => 280.00],  // Taladro
        'ITM-000017' => ['opening' => 20.0, 'cost' => 60.00],   // Aceite Motor Galón
    ];

    public function run(StockLedgerService $ledger): void
    {
        $mainWarehouse = Warehouse::where('code', 'ALM01')->first()
            ?? Warehouse::where('is_default', true)->first();
        $storeWarehouse = Warehouse::where('code', 'ALM02')->first();

        if ($mainWarehouse === null) {
            return;
        }

        // Si ya existen tomas de inventario demo, no duplicar
        if (InventoryCount::where('number', 'like', 'TI-%')->exists()) {
            return;
        }

        $admin = User::where('email', 'admin@example.com')->first();
        $catalog = CatalogItem::with('defaultPresentation')->get()->keyBy('code');

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $openingDate = $startOfMonth->copy()->subDay()->setTime(18, 0);

        DB::transaction(function () use (
            $ledger,
            $mainWarehouse,
            $storeWarehouse,
            $admin,
            $catalog,
            $openingDate,
            $now
        ) {
            // =========================================================================
            // TI-000001: Toma de Inventario Inicial de apertura (Cerrada)
            // =========================================================================
            $ti1 = InventoryCount::updateOrCreate(
                ['number' => 'TI-000001'],
                [
                    'branch_id' => $mainWarehouse->branch_id,
                    'warehouse_id' => $mainWarehouse->id,
                    'category_id' => null, // Todo el almacén
                    'count_date' => $openingDate->toDateString(),
                    'status' => InventoryCountStatus::Closed,
                    'notes' => 'Levantamiento físico de inventario para apertura de gestión 2026',
                    'created_by' => $admin?->id,
                    'closed_by' => $admin?->id,
                    'closed_at' => $openingDate->copy()->addMinutes(45),
                ]
            );
            SequentialCodeGenerator::reserve('inventory_count', $ti1->number);

            foreach (self::INITIAL_STOCK as $code => $data) {
                $item = $catalog->get($code);
                if ($item === null) {
                    continue;
                }

                $counted = $data['opening'];
                $unitCost = $data['cost'];
                $diff = $counted;
                $diffValue = round($diff * $unitCost, 4);

                InventoryCountItem::updateOrCreate(
                    [
                        'inventory_count_id' => $ti1->id,
                        'catalog_item_id' => $item->id,
                    ],
                    [
                        'system_quantity' => 0.0,
                        'counted_quantity' => $counted,
                        'closing_system_quantity' => 0.0,
                        'difference' => $diff,
                        'unit_cost' => $unitCost,
                        'difference_value' => $diffValue,
                        'counted_by' => $admin?->id,
                        'counted_at' => $openingDate,
                    ]
                );
            }

            // Vincular formalmente la Nota de Ingreso de Apertura NI-000001 con esta toma física
            $openingNote = WarehouseNote::where('number', 'NI-000001')->first();
            if ($openingNote !== null) {
                $openingNote->update(['inventory_count_id' => $ti1->id]);
            }

            // =========================================================================
            // TI-000002: Toma de Inventario Cíclica en Tienda Miraflores (Cerrada con Ajustes)
            // =========================================================================
            if ($storeWarehouse !== null) {
                $storeDate = $openingDate->copy()->addDays(5)->setTime(16, 30);
                $closeDate = $storeDate->copy()->addHour();

                $ti2 = InventoryCount::updateOrCreate(
                    ['number' => 'TI-000002'],
                    [
                        'branch_id' => $storeWarehouse->branch_id,
                        'warehouse_id' => $storeWarehouse->id,
                        'category_id' => null,
                        'count_date' => $storeDate->toDateString(),
                        'status' => InventoryCountStatus::Closed,
                        'notes' => 'Conteo físico periódico de periféricos y suministros en Tienda Miraflores',
                        'created_by' => $admin?->id,
                        'closed_by' => $admin?->id,
                        'closed_at' => $closeDate,
                    ]
                );
                SequentialCodeGenerator::reserve('inventory_count', $ti2->number);

                // Ítems presentes en tienda tras traslado TR-000001:
                // ITM-000003 (Teclado): sistema = 10.0, contado = 9.0 (faltante de 1 unidad por merma)
                // ITM-000004 (Mouse): sistema = 12.0, contado = 13.0 (sobrante de 1 unidad por empaque doble)
                $storeItems = [
                    'ITM-000001' => ['counted' => 3.0,  'diff' => 0.0],
                    'ITM-000002' => ['counted' => 5.0,  'diff' => 0.0],
                    'ITM-000003' => ['counted' => 9.0,  'diff' => -1.0], // Faltante
                    'ITM-000004' => ['counted' => 13.0, 'diff' => 1.0],  // Sobrante
                    'ITM-000005' => ['counted' => 8.0,  'diff' => 0.0],
                    'ITM-000008' => ['counted' => 15.0, 'diff' => 0.0],
                ];

                foreach ($storeItems as $code => $data) {
                    $item = $catalog->get($code);
                    if ($item === null) {
                        continue;
                    }

                    $stock = Stock::where('warehouse_id', $storeWarehouse->id)
                        ->where('catalog_item_id', $item->id)
                        ->first();

                    $systemQty = $stock !== null ? (float) $stock->quantity : ($data['counted'] - $data['diff']);
                    $avgCost = $stock !== null ? (float) $stock->avg_cost : (float) ($item->last_purchase_cost ?? 100.0);
                    $countedQty = $data['counted'];
                    $diff = $data['diff'];
                    $diffVal = round($diff * $avgCost, 4);

                    InventoryCountItem::updateOrCreate(
                        [
                            'inventory_count_id' => $ti2->id,
                            'catalog_item_id' => $item->id,
                        ],
                        [
                            'system_quantity' => $systemQty,
                            'counted_quantity' => $countedQty,
                            'closing_system_quantity' => $systemQty,
                            'difference' => $diff,
                            'unit_cost' => $avgCost,
                            'difference_value' => $diffVal,
                            'counted_by' => $admin?->id,
                            'counted_at' => $storeDate,
                        ]
                    );
                }

                // Generar Nota de Ingreso por Ajuste (Sobrante: NI-000004)
                $reasonIn = WarehouseReason::where('code', SystemReason::CountAdjustmentIn->value)->first();
                $mouseItem = $catalog->get('ITM-000004');
                if ($reasonIn !== null && $mouseItem !== null && ! WarehouseNote::where('number', 'NI-000004')->exists()) {
                    $noteIn = WarehouseNote::create([
                        'number' => 'NI-000004',
                        'type' => WarehouseNoteType::In,
                        'status' => WarehouseNoteStatus::Approved,
                        'branch_id' => $storeWarehouse->branch_id,
                        'warehouse_id' => $storeWarehouse->id,
                        'reason_id' => $reasonIn->id,
                        'inventory_count_id' => $ti2->id,
                        'document_date' => $storeDate->toDateString(),
                        'reference_document' => $ti2->number,
                        'observation' => "Ajuste de ingreso generado por toma de inventario {$ti2->number}",
                        'total_cost' => 65.00,
                        'created_by' => $admin?->id,
                        'approved_by' => $admin?->id,
                        'approved_at' => $closeDate,
                    ]);
                    SequentialCodeGenerator::reserve('warehouse_note_in', $noteIn->number);

                    $mousePres = $mouseItem->defaultPresentation;
                    $inData = new StockMovementData(
                        warehouseId: $storeWarehouse->id,
                        catalogItemId: $mouseItem->id,
                        baseQuantity: 1.0,
                        type: MovementType::AdjustmentIn,
                        source: $noteIn,
                        sourceNumber: $noteIn->number,
                        unitCost: 65.00,
                        presentationId: $mousePres?->id,
                        presentationQuantity: 1.0,
                        reasonCode: SystemReason::CountAdjustmentIn->value,
                        notes: "Ajuste sobrante por toma de inventario {$ti2->number}",
                        movedAt: $closeDate,
                    );

                    $inMovement = $ledger->in($inData);

                    WarehouseNoteItem::create([
                        'warehouse_note_id' => $noteIn->id,
                        'line' => 1,
                        'catalog_item_id' => $mouseItem->id,
                        'item_presentation_id' => $mousePres?->id,
                        'quantity' => 1.0,
                        'base_quantity' => 1.0,
                        'unit_cost' => (float) $inMovement->unit_cost,
                        'base_unit_cost' => (float) $inMovement->unit_cost,
                        'total_cost' => (float) $inMovement->total_cost,
                        'notes' => 'Ajuste sobrante de conteo',
                    ]);

                    $noteIn->update(['total_cost' => (float) $inMovement->total_cost]);
                }

                // Generar Nota de Salida por Ajuste (Faltante: NS-000005)
                $reasonOut = WarehouseReason::where('code', SystemReason::CountAdjustmentOut->value)->first();
                $kbItem = $catalog->get('ITM-000003');
                if ($reasonOut !== null && $kbItem !== null && ! WarehouseNote::where('number', 'NS-000005')->exists()) {
                    $noteOut = WarehouseNote::create([
                        'number' => 'NS-000005',
                        'type' => WarehouseNoteType::Out,
                        'status' => WarehouseNoteStatus::Approved,
                        'branch_id' => $storeWarehouse->branch_id,
                        'warehouse_id' => $storeWarehouse->id,
                        'reason_id' => $reasonOut->id,
                        'inventory_count_id' => $ti2->id,
                        'document_date' => $storeDate->toDateString(),
                        'reference_document' => $ti2->number,
                        'observation' => "Ajuste de salida generado por toma de inventario {$ti2->number}",
                        'total_cost' => 160.00,
                        'created_by' => $admin?->id,
                        'approved_by' => $admin?->id,
                        'approved_at' => $closeDate,
                    ]);
                    SequentialCodeGenerator::reserve('warehouse_note_out', $noteOut->number);

                    $kbPres = $kbItem->defaultPresentation;
                    $outData = new StockMovementData(
                        warehouseId: $storeWarehouse->id,
                        catalogItemId: $kbItem->id,
                        baseQuantity: 1.0,
                        type: MovementType::AdjustmentOut,
                        source: $noteOut,
                        sourceNumber: $noteOut->number,
                        presentationId: $kbPres?->id,
                        presentationQuantity: 1.0,
                        reasonCode: SystemReason::CountAdjustmentOut->value,
                        notes: "Ajuste faltante por toma de inventario {$ti2->number}",
                        movedAt: $closeDate,
                    );

                    $outMovement = $ledger->out($outData);

                    WarehouseNoteItem::create([
                        'warehouse_note_id' => $noteOut->id,
                        'line' => 1,
                        'catalog_item_id' => $kbItem->id,
                        'item_presentation_id' => $kbPres?->id,
                        'quantity' => 1.0,
                        'base_quantity' => 1.0,
                        'unit_cost' => (float) $outMovement->unit_cost,
                        'base_unit_cost' => (float) $outMovement->unit_cost,
                        'total_cost' => (float) $outMovement->total_cost,
                        'notes' => 'Ajuste faltante de conteo',
                    ]);

                    $noteOut->update(['total_cost' => (float) $outMovement->total_cost]);
                }
            }

            // =========================================================================
            // TI-000003: Toma de Inventario en Proceso en Almacén Principal (Counting)
            // =========================================================================
            $techCategory = Category::where('code', 'like', 'TEC%')->first();

            $ti3 = InventoryCount::updateOrCreate(
                ['number' => 'TI-000003'],
                [
                    'branch_id' => $mainWarehouse->branch_id,
                    'warehouse_id' => $mainWarehouse->id,
                    'category_id' => $techCategory?->id,
                    'count_date' => $now->toDateString(),
                    'status' => InventoryCountStatus::Counting,
                    'notes' => 'Conteo físico selectivo de componentes y periféricos de cómputo en pasillo A',
                    'created_by' => $admin?->id,
                ]
            );
            SequentialCodeGenerator::reserve('inventory_count', $ti3->number);

            $countingItems = [
                'ITM-000001' => ['counted' => 10.0],  // Laptop: ya contada conforme
                'ITM-000002' => ['counted' => 16.0],  // Monitor: ya contado
                'ITM-000003' => ['counted' => null],  // Teclado: pendiente de conteo
                'ITM-000004' => ['counted' => null],  // Mouse: pendiente de conteo
                'ITM-000005' => ['counted' => null],  // SSD: pendiente de conteo
                'ITM-000006' => ['counted' => null],  // RAM: pendiente de conteo
            ];

            foreach ($countingItems as $code => $data) {
                $item = $catalog->get($code);
                if ($item === null) {
                    continue;
                }

                $stock = Stock::where('warehouse_id', $mainWarehouse->id)
                    ->where('catalog_item_id', $item->id)
                    ->first();

                $systemQty = $stock !== null ? (float) $stock->quantity : 0.0;
                $avgCost = $stock !== null ? (float) $stock->avg_cost : 0.0;
                $countedQty = $data['counted'];

                InventoryCountItem::updateOrCreate(
                    [
                        'inventory_count_id' => $ti3->id,
                        'catalog_item_id' => $item->id,
                    ],
                    [
                        'system_quantity' => $systemQty,
                        'counted_quantity' => $countedQty,
                        'closing_system_quantity' => null,
                        'difference' => null,
                        'unit_cost' => $avgCost,
                        'difference_value' => null,
                        'counted_by' => $countedQty !== null ? $admin?->id : null,
                        'counted_at' => $countedQty !== null ? $now : null,
                    ]
                );
            }

            // =========================================================================
            // TI-000004: Toma de Inventario Anulada (Cancelled)
            // =========================================================================
            $cancelledDate = $now->copy()->subDays(6);
            $ti4 = InventoryCount::updateOrCreate(
                ['number' => 'TI-000004'],
                [
                    'branch_id' => $mainWarehouse->branch_id,
                    'warehouse_id' => $mainWarehouse->id,
                    'category_id' => null,
                    'count_date' => $cancelledDate->toDateString(),
                    'status' => InventoryCountStatus::Cancelled,
                    'notes' => 'Toma física cancelada por labores de desinfección y mantenimiento de almacén',
                    'created_by' => $admin?->id,
                    'cancelled_by' => $admin?->id,
                    'cancelled_at' => $cancelledDate->copy()->addHours(2),
                ]
            );
            SequentialCodeGenerator::reserve('inventory_count', $ti4->number);

            $cancelItem = $catalog->get('ITM-000008');
            if ($cancelItem !== null) {
                InventoryCountItem::updateOrCreate(
                    [
                        'inventory_count_id' => $ti4->id,
                        'catalog_item_id' => $cancelItem->id,
                    ],
                    [
                        'system_quantity' => 100.0,
                        'counted_quantity' => null,
                        'closing_system_quantity' => null,
                        'difference' => null,
                        'unit_cost' => 11.50,
                        'difference_value' => null,
                    ]
                );
            }
        });
    }
}
