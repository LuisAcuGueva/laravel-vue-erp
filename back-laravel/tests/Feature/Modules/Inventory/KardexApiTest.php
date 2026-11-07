<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class KardexApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        $this->seedUbigeoFixture();
    }

    private function authenticateAsAdmin(?Branch $branch = null): User
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $adminRole->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        if ($branch !== null) {
            $user->branches()->sync([$branch->id => ['is_default' => true]]);
            $this->withHeader('X-Branch-Id', $branch->id);
        }

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    private function createItem(Unit $unit): CatalogItem
    {
        $category = Category::factory()->create();

        /** @var CatalogItem $item */
        $item = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Kardex Test Item',
            'track_stock' => true,
        ]);

        ItemPresentation::factory()->create([
            'catalog_item_id' => $item->id,
            'unit_id' => $unit->id,
            'name' => $unit->name,
            'factor' => 1.0,
            'is_base' => true,
            'is_default' => true,
        ]);

        return $item;
    }

    public function test_kardex_opening_balance_and_movements_in_range(): void
    {
        $branch = Branch::factory()->create();
        $this->authenticateAsAdmin($branch);
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $item = $this->createItem($unit);

        $ledger = app(StockLedgerService::class);

        // Movement before period (2026-10-01)
        Carbon::setTestNow('2026-10-01 10:00:00');
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

        // Movement within period (2026-10-15)
        Carbon::setTestNow('2026-10-15 14:00:00');
        DB::transaction(function () use ($ledger, $warehouse, $item) {
            $ledger->in(new StockMovementData(
                warehouseId: $warehouse->id,
                catalogItemId: $item->id,
                baseQuantity: 10,
                type: MovementType::Purchase,
                source: $warehouse,
                sourceNumber: 'COMPRA-01',
                unitCost: 7.0,
            ));
        });

        // Query range 2026-10-10 to 2026-10-20
        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->authenticateAsAdmin($branch);

        $response = $this->getJson("/api/v1/inventory/kardex?warehouse_id={$warehouse->id}&catalog_item_id={$item->id}&date_from=2026-10-10&date_to=2026-10-20");
        $response->assertOk()
            ->assertJsonPath('data.opening_balance.quantity', 10)
            ->assertJsonPath('data.opening_balance.unit_cost', 5)
            ->assertJsonPath('data.opening_balance.total', 50)
            ->assertJsonCount(1, 'data.movements')
            ->assertJsonPath('data.movements.0.source_number', 'COMPRA-01')
            ->assertJsonPath('data.closing_balance.quantity', 20)
            ->assertJsonPath('data.closing_balance.unit_cost', 6)
            ->assertJsonPath('data.closing_balance.total', 120);

        Carbon::setTestNow();
    }

    public function test_kardex_date_range_exceeding_366_days_returns_422(): void
    {
        $branch = Branch::factory()->create();
        $this->authenticateAsAdmin($branch);
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $item = $this->createItem($unit);

        $response = $this->getJson("/api/v1/inventory/kardex?warehouse_id={$warehouse->id}&catalog_item_id={$item->id}&date_from=2025-01-01&date_to=2026-01-15");
        $response->assertStatus(422)
            ->assertJsonValidationErrors('date_to');
    }

    public function test_kardex_export_csv(): void
    {
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id, 'code' => 'ALM01']);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $item = $this->createItem($unit);

        $ledger = app(StockLedgerService::class);
        Carbon::setTestNow('2026-10-05 10:00:00');
        DB::transaction(function () use ($ledger, $warehouse, $item) {
            $ledger->in(new StockMovementData(
                warehouseId: $warehouse->id,
                catalogItemId: $item->id,
                baseQuantity: 10,
                type: MovementType::Opening,
                source: $warehouse,
                sourceNumber: 'INIT',
                unitCost: 5.0,
            ));
        });

        Carbon::setTestNow('2026-10-06 10:00:00');
        $this->authenticateAsAdmin($branch);

        $response = $this->getJson("/api/v1/inventory/kardex/export?warehouse_id={$warehouse->id}&catalog_item_id={$item->id}&date_from=2026-10-01&date_to=2026-10-31");
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Fecha / Hora', $content);
        $this->assertStringContainsString('SALDO INICIAL', $content);
        $this->assertStringContainsString('INIT', $content);

        Carbon::setTestNow();
    }
}
