<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Maintenance\Models\Branch;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class VerifyStockCommandTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        $this->seedUbigeoFixture();
    }

    public function test_verify_stock_command_succeeds_when_consistent(): void
    {
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $category = Category::factory()->create();

        $item = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'track_stock' => true,
        ]);
        ItemPresentation::factory()->create([
            'catalog_item_id' => $item->id,
            'unit_id' => $unit->id,
            'is_base' => true,
            'is_default' => true,
        ]);

        $ledger = app(StockLedgerService::class);
        DB::transaction(function () use ($ledger, $warehouse, $item) {
            $ledger->in(new StockMovementData(
                warehouseId: $warehouse->id,
                catalogItemId: $item->id,
                baseQuantity: 10,
                type: MovementType::Opening,
                source: $warehouse,
                sourceNumber: 'APERTURA',
                unitCost: 5.0,
            ));

            $ledger->out(new StockMovementData(
                warehouseId: $warehouse->id,
                catalogItemId: $item->id,
                baseQuantity: 4,
                type: MovementType::Sale,
                source: $warehouse,
                sourceNumber: 'VENTA-01',
            ));
        });

        $this->artisan('inventory:verify-stock')
            ->expectsOutput('Sin diferencias entre stock y kardex.')
            ->assertSuccessful();
    }

    public function test_verify_stock_command_fails_when_inconsistent(): void
    {
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $category = Category::factory()->create();

        $item = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'track_stock' => true,
        ]);
        ItemPresentation::factory()->create([
            'catalog_item_id' => $item->id,
            'unit_id' => $unit->id,
            'is_base' => true,
            'is_default' => true,
        ]);

        $ledger = app(StockLedgerService::class);
        DB::transaction(function () use ($ledger, $warehouse, $item) {
            $ledger->in(new StockMovementData(
                warehouseId: $warehouse->id,
                catalogItemId: $item->id,
                baseQuantity: 10,
                type: MovementType::Opening,
                source: $warehouse,
                sourceNumber: 'APERTURA',
                unitCost: 5.0,
            ));
        });

        // Corrupt stock quantity directly
        Stock::where('warehouse_id', $warehouse->id)
            ->where('catalog_item_id', $item->id)
            ->update(['quantity' => 99]);

        $this->artisan('inventory:verify-stock')
            ->expectsOutputToContain('Se encontraron 1 discrepancias')
            ->assertFailed();

        // With --fix
        $this->artisan('inventory:verify-stock --fix')
            ->expectsOutputToContain('Modo --fix')
            ->assertFailed();
    }
}
