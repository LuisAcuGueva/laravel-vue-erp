<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseNote;
use App\Modules\Inventory\Services\KardexQueryService;
use Carbon\Carbon;
use Database\Seeders\AreaSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\InventoryMastersSeeder;
use Database\Seeders\ProviderSeeder;
use Database\Seeders\PurchasesDemoSeeder;
use Database\Seeders\PurchasesReceiptsDemoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StockDemoSeeder;
use Database\Seeders\SystemParameterSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SystemParameterSeeder::class,
            UserSeeder::class,
            BranchSeeder::class,
            AreaSeeder::class,
            InventoryMastersSeeder::class,
            CatalogSeeder::class,
            FinanceMastersSeeder::class,
            ProviderSeeder::class,
            PurchasesDemoSeeder::class,
            PurchasesReceiptsDemoSeeder::class,
        ]);
    }

    public function test_stock_demo_seeder_populates_stocks_and_kardex_movements(): void
    {
        $this->seed(StockDemoSeeder::class);

        // Verifica que se hayan creado stocks
        $stockCount = Stock::count();
        $this->assertGreaterThan(0, $stockCount);

        // Verifica que se hayan creado notas de almacén de ingreso, salida y traslado
        $notesCount = WarehouseNote::count();
        $this->assertGreaterThan(0, $notesCount);
        $this->assertTrue(WarehouseNote::where('number', 'NI-000001')->exists());
        $this->assertTrue(WarehouseNote::where('number', 'TR-000001')->exists());
        $this->assertTrue(WarehouseNote::where('number', 'NS-000001')->exists());
        $this->assertTrue(WarehouseNote::where('status', 'draft')->exists());
        $this->assertTrue(WarehouseNote::where('status', 'cancelled')->exists());

        // Verifica existencias tanto en Almacén Principal como en Tienda
        $mainWarehouse = Warehouse::where('code', 'ALM01')->firstOrFail();
        $storeWarehouse = Warehouse::where('code', 'ALM02')->firstOrFail();

        $mainStocks = Stock::where('warehouse_id', $mainWarehouse->id)->where('quantity', '>', 0)->count();
        $storeStocks = Stock::where('warehouse_id', $storeWarehouse->id)->where('quantity', '>', 0)->count();

        $this->assertGreaterThan(0, $mainStocks);
        $this->assertGreaterThan(0, $storeStocks);

        // Verifica que existan movimientos en ambas direcciones
        $inCount = KardexMovement::where('direction', MovementDirection::In)->count();
        $outCount = KardexMovement::where('direction', MovementDirection::Out)->count();
        $this->assertGreaterThan(0, $inCount);
        $this->assertGreaterThan(0, $outCount);

        // Verifica traslados con transfer_group_id
        $transferOut = KardexMovement::where('movement_type', MovementType::TransferOut)->first();
        $transferIn = KardexMovement::where('movement_type', MovementType::TransferIn)->first();
        $this->assertNotNull($transferOut);
        $this->assertNotNull($transferIn);
        $this->assertNotNull($transferOut->transfer_group_id);
        $this->assertNotNull($transferIn->transfer_group_id);

        // Verifica alerta de stock bajo (Taladro ITM-000016)
        $drill = CatalogItem::where('code', 'ITM-000016')->firstOrFail();
        $drillStock = Stock::where('warehouse_id', $mainWarehouse->id)
            ->where('catalog_item_id', $drill->id)
            ->firstOrFail();

        $this->assertTrue($drillStock->is_below_min);
        $this->assertLessThan($drillStock->min_stock, $drillStock->quantity);

        // Verifica que el costo promedio ponderado de la laptop (ITM-000001) se haya recalculado tras compra
        $laptop = CatalogItem::where('code', 'ITM-000001')->firstOrFail();
        $laptopStock = Stock::where('warehouse_id', $mainWarehouse->id)
            ->where('catalog_item_id', $laptop->id)
            ->firstOrFail();

        // 10 iniciales @ 2850 + 5 compradas @ 2950 = 15 total por S/ 43,250 -> prom 2883.333333
        // Luego salen 3 por traslado (las ventas ya no las genera este seeder) -> quedan 12 unidades al mismo costo promedio
        $this->assertEquals(12.0, $laptopStock->quantity);
        $this->assertEqualsWithDelta(2883.3333, $laptopStock->avg_cost, 0.001);

        // Verifica que la consulta de Kardex por rango de fechas retorne saldo inicial y movimientos
        /** @var KardexQueryService $kardexService */
        $kardexService = app(KardexQueryService::class);
        $summary = $kardexService->summary(
            $mainWarehouse->id,
            $laptop->id,
            Carbon::now()->startOfMonth(),
            Carbon::now()
        );

        $this->assertGreaterThan(0, $summary['opening_balance']['quantity']);
        $this->assertNotEmpty($summary['movements']);
        $this->assertEquals(12.0, $summary['closing_balance']['quantity']);
    }

    public function test_stock_demo_seeder_is_idempotent(): void
    {
        $this->seed(StockDemoSeeder::class);

        $initialStockCount = Stock::count();
        $initialKardexCount = KardexMovement::count();
        $initialNotesCount = WarehouseNote::count();
        $initialTotalQuantity = Stock::sum('quantity');

        // Ejecutar de nuevo no debe lanzar excepción de inmutabilidad ni duplicar kardex ni notas
        $this->seed(StockDemoSeeder::class);

        $this->assertSame($initialStockCount, Stock::count());
        $this->assertSame($initialKardexCount, KardexMovement::count());
        $this->assertSame($initialNotesCount, WarehouseNote::count());
        $this->assertEquals($initialTotalQuantity, Stock::sum('quantity'));
    }
}
