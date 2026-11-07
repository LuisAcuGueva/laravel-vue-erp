<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\CatalogItemType;
use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Maintenance\Models\Branch;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class StockLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StockLedgerService $ledger;

    protected Branch $branch;

    protected Warehouse $warehouseA;

    protected Warehouse $warehouseB;

    protected CatalogItem $item;

    protected CatalogItem $serviceItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemParameterSeeder::class);
        $this->ledger = app(StockLedgerService::class);

        $this->branch = Branch::create([
            'name' => 'Sede Principal',
            'code' => 'SED-001',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->warehouseA = Warehouse::create([
            'branch_id' => $this->branch->id,
            'name' => 'Almacén Central',
            'code' => 'ALM-001',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'branch_id' => $this->branch->id,
            'name' => 'Almacén Secundario',
            'code' => 'ALM-002',
            'is_active' => true,
            'is_default' => false,
        ]);

        $unitCategory = UnitCategory::create([
            'name' => 'Unidades',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'unit_category_id' => $unitCategory->id,
            'name' => 'Unidad',
            'code' => 'NIU',
            'symbol' => 'UND',
            'factor' => 1,
            'allow_decimals' => false,
            'is_base' => true,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Repuestos',
            'code' => 'REP',
            'is_active' => true,
        ]);

        $this->item = CatalogItem::create([
            'code' => 'ITM-000001',
            'name' => 'Filtro de Aceite',
            'type' => CatalogItemType::Product,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'tax_type' => TaxType::Taxable,
            'track_stock' => true,
            'is_sellable' => true,
            'is_purchasable' => true,
            'is_active' => true,
        ]);

        $this->serviceItem = CatalogItem::create([
            'code' => 'SRV-000001',
            'name' => 'Instalación de Filtro',
            'type' => CatalogItemType::Service,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'tax_type' => TaxType::Taxable,
            'track_stock' => false,
            'is_sellable' => true,
            'is_purchasable' => false,
            'is_active' => true,
        ]);
    }

    public function test_weighted_average_cost_calculation_on_entries_and_exits(): void
    {
        DB::transaction(function () {
            // Entrada 10 @ 5 -> stock 10, promedio 5
            $m1 = $this->ledger->in(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->item->id,
                baseQuantity: 10.0,
                type: MovementType::Opening,
                source: $this->warehouseA,
                sourceNumber: 'INI-001',
                unitCost: 5.0,
            ));

            $stock = Stock::where('warehouse_id', $this->warehouseA->id)
                ->where('catalog_item_id', $this->item->id)
                ->firstOrFail();

            $this->assertEquals(10.0, $stock->quantity);
            $this->assertEquals(5.0, $stock->avg_cost);
            $this->assertEquals(5.0, $m1->balance_unit_cost);
            $this->assertEquals(50.0, $m1->balance_total_cost);

            // Entrada 10 @ 7 -> stock 20, promedio 6
            $m2 = $this->ledger->in(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->item->id,
                baseQuantity: 10.0,
                type: MovementType::Purchase,
                source: $this->warehouseA,
                sourceNumber: 'OC-001',
                unitCost: 7.0,
            ));

            $stock->refresh();
            $this->assertEquals(20.0, $stock->quantity);
            $this->assertEquals(6.0, $stock->avg_cost);
            $this->assertEquals(6.0, $m2->balance_unit_cost);
            $this->assertEquals(120.0, $m2->balance_total_cost);

            // Salida 5 -> costo 6, saldo 15 @ 6, total 90
            $m3 = $this->ledger->out(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->item->id,
                baseQuantity: 5.0,
                type: MovementType::Sale,
                source: $this->warehouseA,
                sourceNumber: 'V-001',
            ));

            $stock->refresh();
            $this->assertEquals(15.0, $stock->quantity);
            $this->assertEquals(6.0, $stock->avg_cost);
            $this->assertEquals(6.0, $m3->unit_cost);
            $this->assertEquals(30.0, $m3->total_cost);
            $this->assertEquals(15.0, $m3->balance_quantity);
            $this->assertEquals(6.0, $m3->balance_unit_cost);
            $this->assertEquals(90.0, $m3->balance_total_cost);
        });
    }

    public function test_insufficient_stock_exception_when_negative_stock_not_allowed(): void
    {
        $this->expectException(InsufficientStockException::class);

        DB::transaction(function () {
            $this->ledger->out(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->item->id,
                baseQuantity: 5.0,
                type: MovementType::Sale,
                source: $this->warehouseA,
                sourceNumber: 'V-001',
            ));
        });
    }

    public function test_negative_stock_allowed_when_parameter_enabled(): void
    {
        SystemParameter::where('group_key', 'inventory')
            ->where('item_key', 'allow_negative_stock')
            ->update(['value' => 'true']);
        Cache::flush();

        DB::transaction(function () {
            $m = $this->ledger->out(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->item->id,
                baseQuantity: 5.0,
                type: MovementType::Sale,
                source: $this->warehouseA,
                sourceNumber: 'V-001',
            ));

            $stock = Stock::where('warehouse_id', $this->warehouseA->id)
                ->where('catalog_item_id', $this->item->id)
                ->firstOrFail();

            $this->assertEquals(-5.0, $stock->quantity);
            $this->assertEquals(-5.0, $m->balance_quantity);
        });
    }

    public function test_transfer_shares_transfer_group_id_and_preserves_cost(): void
    {
        DB::transaction(function () {
            // Inicializar stock en almacén A: 10 @ 12.50
            $this->ledger->in(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->item->id,
                baseQuantity: 10.0,
                type: MovementType::Opening,
                source: $this->warehouseA,
                sourceNumber: 'INI-001',
                unitCost: 12.50,
            ));

            // Trasladar 4 unidades de A a B
            [$outMove, $inMove] = $this->ledger->transfer(
                new StockMovementData(
                    warehouseId: $this->warehouseA->id,
                    catalogItemId: $this->item->id,
                    baseQuantity: 4.0,
                    type: MovementType::TransferOut,
                    source: $this->warehouseA,
                    sourceNumber: 'TR-001',
                ),
                $this->warehouseB->id
            );

            $this->assertNotNull($outMove->transfer_group_id);
            $this->assertSame($outMove->transfer_group_id, $inMove->transfer_group_id);
            $this->assertEquals(MovementDirection::Out, $outMove->direction);
            $this->assertEquals(MovementDirection::In, $inMove->direction);
            $this->assertEquals(12.50, $outMove->unit_cost);
            $this->assertEquals(12.50, $inMove->unit_cost);

            $stockA = Stock::where('warehouse_id', $this->warehouseA->id)
                ->where('catalog_item_id', $this->item->id)
                ->firstOrFail();
            $stockB = Stock::where('warehouse_id', $this->warehouseB->id)
                ->where('catalog_item_id', $this->item->id)
                ->firstOrFail();

            $this->assertEquals(6.0, $stockA->quantity);
            $this->assertEquals(4.0, $stockB->quantity);
            $this->assertEquals(12.50, $stockB->avg_cost);
        });
    }

    public function test_reversal_restores_quantity_and_average_cost_and_prevents_double_reversal(): void
    {
        DB::transaction(function () {
            $m1 = $this->ledger->in(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->item->id,
                baseQuantity: 10.0,
                type: MovementType::Opening,
                source: $this->warehouseA,
                sourceNumber: 'INI-001',
                unitCost: 5.0,
            ));

            $m2 = $this->ledger->in(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->item->id,
                baseQuantity: 10.0,
                type: MovementType::Purchase,
                source: $this->warehouseA,
                sourceNumber: 'OC-001',
                unitCost: 7.0,
            ));

            $stock = Stock::where('warehouse_id', $this->warehouseA->id)
                ->where('catalog_item_id', $this->item->id)
                ->firstOrFail();

            $this->assertEquals(20.0, $stock->quantity);
            $this->assertEquals(6.0, $stock->avg_cost);

            // Revertir m2 (10 @ 7)
            $reversal = $this->ledger->reverse($m2, $this->warehouseA, 'REV-001');

            $this->assertSame($m2->id, $reversal->reverses_id);
            $this->assertEquals(MovementType::ReversalOut, $reversal->movement_type);

            $stock->refresh();
            $this->assertEquals(10.0, $stock->quantity);
            $this->assertEquals(5.0, $stock->avg_cost);

            // Segunda reversión debe fallar
            $this->expectException(LogicException::class);
            $this->ledger->reverse($m2, $this->warehouseA, 'REV-002');
        });
    }

    public function test_kardex_movement_is_immutable(): void
    {
        $movement = DB::transaction(function () {
            return $this->ledger->in(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->item->id,
                baseQuantity: 10.0,
                type: MovementType::Opening,
                source: $this->warehouseA,
                sourceNumber: 'INI-001',
                unitCost: 5.0,
            ));
        });

        $this->expectException(LogicException::class);
        $movement->update(['notes' => 'Modificado ilegalmente']);
    }

    public function test_kardex_movement_cannot_be_deleted(): void
    {
        $movement = DB::transaction(function () {
            return $this->ledger->in(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->item->id,
                baseQuantity: 10.0,
                type: MovementType::Opening,
                source: $this->warehouseA,
                sourceNumber: 'INI-001',
                unitCost: 5.0,
            ));
        });

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_requires_database_transaction(): void
    {
        DB::rollBack();

        try {
            $this->expectException(LogicException::class);

            $this->ledger->in(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->item->id,
                baseQuantity: 10.0,
                type: MovementType::Opening,
                source: $this->warehouseA,
                sourceNumber: 'INI-001',
                unitCost: 5.0,
            ));
        } finally {
            DB::beginTransaction();
        }
    }

    public function test_service_items_are_rejected_with_rule_exception(): void
    {
        $this->expectException(InventoryRuleException::class);

        DB::transaction(function () {
            $this->ledger->in(new StockMovementData(
                warehouseId: $this->warehouseA->id,
                catalogItemId: $this->serviceItem->id,
                baseQuantity: 1.0,
                type: MovementType::Opening,
                source: $this->warehouseA,
                sourceNumber: 'INI-001',
                unitCost: 50.0,
            ));
        });
    }
}
