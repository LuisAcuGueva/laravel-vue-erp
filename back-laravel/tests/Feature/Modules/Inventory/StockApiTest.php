<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Audit\Models\Activity;
use App\Modules\Auth\Services\JwtService;
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
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class StockApiTest extends TestCase
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

    private function authenticateAsUser(array $permissions, array $branchIds = [], ?string $activeBranchId = null): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $slug) {
            $perm = Permission::where('slug', $slug)->firstOrFail();
            $user->givePermissionTo($perm);
        }

        if (! empty($branchIds)) {
            $syncData = [];
            foreach ($branchIds as $index => $bId) {
                $syncData[$bId] = ['is_default' => $index === 0];
            }
            $user->branches()->sync($syncData);
        }

        if ($activeBranchId !== null) {
            $this->withHeader('X-Branch-Id', $activeBranchId);
        }

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    private function createItem(string $name, Unit $unit, ?Category $category = null): CatalogItem
    {
        if ($category === null) {
            $category = Category::factory()->create();
        }

        /** @var CatalogItem $item */
        $item = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => $name,
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

    private function createStock(Warehouse $warehouse, CatalogItem $item, float $quantity, float $avgCost, float $minStock = 0, ?float $maxStock = null): Stock
    {
        /** @var Stock $stock */
        $stock = Stock::create([
            'warehouse_id' => $warehouse->id,
            'catalog_item_id' => $item->id,
            'min_stock' => $minStock,
            'max_stock' => $maxStock,
        ]);
        $stock->forceFill([
            'quantity' => $quantity,
            'avg_cost' => $avgCost,
            'last_movement_at' => now(),
        ])->save();

        return $stock;
    }

    public function test_stock_list_for_active_branch_includes_meta_total_value(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $this->authenticateAsAdmin($branchA);

        $warehouseA = Warehouse::factory()->create(['branch_id' => $branchA->id]);
        $warehouseB = Warehouse::factory()->create(['branch_id' => $branchB->id]);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $item1 = $this->createItem('Item 1', $unit);
        $item2 = $this->createItem('Item 2', $unit);

        // Stock in branch A: 10 @ 5 (50) + 20 @ 2 (40) = 90
        $this->createStock($warehouseA, $item1, 10, 5);
        $this->createStock($warehouseA, $item2, 20, 2);

        // Stock in branch B: 100 @ 10 (1000)
        $this->createStock($warehouseB, $item1, 100, 10);

        $response = $this->getJson('/api/v1/inventory/stock');
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total_value', 90);
    }

    public function test_stock_list_filter_below_min(): void
    {
        $branch = Branch::factory()->create();
        $this->authenticateAsAdmin($branch);
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $itemBelow = $this->createItem('Bajo Minimo', $unit);
        $itemOk = $this->createItem('Stock OK', $unit);

        $this->createStock($warehouse, $itemBelow, 2, 10, 5);
        $this->createStock($warehouse, $itemOk, 10, 10, 5);

        $response = $this->getJson('/api/v1/inventory/stock?below_min=1');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.catalog_item.name', 'Bajo Minimo');
    }

    public function test_stock_list_warehouse_from_inaccessible_branch_returns_403(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        // User only has access to branch A
        $this->authenticateAsUser(['stock.view'], [$branchA->id], $branchA->id);

        $warehouseB = Warehouse::factory()->create(['branch_id' => $branchB->id]);

        $response = $this->getJson("/api/v1/inventory/stock?warehouse_id={$warehouseB->id}");
        $response->assertStatus(403);
    }

    public function test_stock_by_item_returns_accessible_warehouses(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $this->authenticateAsUser(['stock.view'], [$branchA->id], $branchA->id);

        $warehouseA1 = Warehouse::factory()->create(['branch_id' => $branchA->id, 'name' => 'Almacen A1']);
        $warehouseA2 = Warehouse::factory()->create(['branch_id' => $branchA->id, 'name' => 'Almacen A2']);
        $warehouseB = Warehouse::factory()->create(['branch_id' => $branchB->id, 'name' => 'Almacen B']);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $item = $this->createItem('Test Item', $unit);

        $this->createStock($warehouseA1, $item, 15, 4);

        $response = $this->getJson("/api/v1/inventory/stock/by-item/{$item->id}");
        $response->assertOk()
            ->assertJsonCount(2, 'data') // A1 and A2, but not B
            ->assertJsonPath('data.0.warehouse.id', $warehouseA1->id)
            ->assertJsonPath('data.0.quantity', 15);
    }

    public function test_update_stock_limits_success_and_validation(): void
    {
        $branch = Branch::factory()->create();
        $this->authenticateAsAdmin($branch);
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $item = $this->createItem('Limit Item', $unit);

        // Valid update
        $res = $this->putJson('/api/v1/inventory/stock/limits', [
            'warehouse_id' => $warehouse->id,
            'catalog_item_id' => $item->id,
            'min_stock' => 10,
            'max_stock' => 50,
        ]);
        $res->assertOk()
            ->assertJsonPath('data.min_stock', 10)
            ->assertJsonPath('data.max_stock', 50);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Stock::class,
            'subject_id' => $res->json('data.id'),
            'event' => 'created',
        ]);

        // Cambiar límites registra el valor anterior y el nuevo
        $this->putJson('/api/v1/inventory/stock/limits', [
            'warehouse_id' => $warehouse->id,
            'catalog_item_id' => $item->id,
            'min_stock' => 15,
            'max_stock' => 50,
        ])->assertOk();

        $activity = Activity::query()
            ->where('subject_id', $res->json('data.id'))
            ->where('event', 'updated')
            ->sole();
        $this->assertNotNull($activity->causer_id);
        $this->assertEquals(['min_stock' => 15], $activity->properties['attributes']);
        $this->assertEquals(['min_stock' => 10], $activity->properties['old']);

        // Invalid: max_stock < min_stock -> 422
        $resInvalid = $this->putJson('/api/v1/inventory/stock/limits', [
            'warehouse_id' => $warehouse->id,
            'catalog_item_id' => $item->id,
            'min_stock' => 30,
            'max_stock' => 20,
        ]);
        $resInvalid->assertStatus(422)
            ->assertJsonValidationErrors('max_stock');
    }

    public function test_catalog_lookup_with_warehouse_id_returns_stock_available(): void
    {
        $branch = Branch::factory()->create();
        $this->authenticateAsAdmin($branch);
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $item = $this->createItem('Lookup Item', $unit);

        $this->createStock($warehouse, $item, 25, 6);

        $response = $this->getJson("/api/v1/inventory/catalog-items/lookup?q=Lookup&context=stock&warehouse_id={$warehouse->id}");
        $response->assertOk()
            ->assertJsonPath('data.0.stock.available', 25)
            ->assertJsonPath('data.0.stock.available_presentation', 25);
    }

    public function test_cannot_change_base_unit_of_item_with_kardex_movements(): void
    {
        $branch = Branch::factory()->create();
        $this->authenticateAsAdmin($branch);
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);

        $unitCategory = UnitCategory::factory()->create();
        $unitA = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $unitB = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => false]);
        $item = $this->createItem('Moveable Item', $unitA);

        $ledger = app(StockLedgerService::class);
        DB::transaction(function () use ($ledger, $warehouse, $item) {
            $ledger->in(new StockMovementData(
                warehouseId: $warehouse->id,
                catalogItemId: $item->id,
                baseQuantity: 10,
                type: MovementType::Opening,
                source: $warehouse,
                sourceNumber: 'INIT-001',
                unitCost: 5.0,
            ));
        });

        // Try to change base_unit_id
        $response = $this->putJson("/api/v1/inventory/catalog-items/{$item->id}", [
            'name' => 'Moveable Item Updated',
            'code' => $item->code,
            'type' => $item->type->value,
            'category_id' => $item->category_id,
            'tax_type' => $item->tax_type->value,
            'base_unit_id' => $unitB->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No se puede cambiar la unidad base de un ítem con movimientos de stock.');
    }

    public function test_cannot_modify_presentation_factor_or_delete_presentation_with_kardex_movements(): void
    {
        $branch = Branch::factory()->create();
        $this->authenticateAsAdmin($branch);
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $item = $this->createItem('Item Factor Test', $unit);

        /** @var ItemPresentation $presentation */
        $presentation = ItemPresentation::factory()->create([
            'catalog_item_id' => $item->id,
            'unit_id' => $unit->id,
            'name' => 'Pack x6',
            'factor' => 6.0,
            'is_base' => false,
            'is_default' => false,
        ]);

        $ledger = app(StockLedgerService::class);
        DB::transaction(function () use ($ledger, $warehouse, $item, $presentation) {
            $ledger->in(new StockMovementData(
                warehouseId: $warehouse->id,
                catalogItemId: $item->id,
                baseQuantity: 12,
                type: MovementType::Purchase,
                source: $warehouse,
                sourceNumber: 'COMPRA-01',
                unitCost: 3.0,
                presentationId: $presentation->id,
                presentationQuantity: 2,
            ));
        });

        // Attempt to change factor -> 422
        $resFactor = $this->putJson("/api/v1/inventory/catalog-items/{$item->id}/presentations/{$presentation->id}", [
            'unit_id' => $unit->id,
            'name' => 'Pack x6 Modified',
            'factor' => 8.0,
        ]);
        $resFactor->assertStatus(422)
            ->assertJsonPath('message', 'No se puede modificar el factor de una presentación con movimientos registrados.');

        // Attempt to delete presentation -> 422
        $resDel = $this->deleteJson("/api/v1/inventory/catalog-items/{$item->id}/presentations/{$presentation->id}");
        $resDel->assertStatus(422)
            ->assertJsonPath('message', 'No se puede eliminar una presentación con movimientos registrados.');
    }

    public function test_cannot_delete_warehouse_with_stock_or_kardex_movements(): void
    {
        $branch = Branch::factory()->create();
        $this->authenticateAsAdmin($branch);
        $warehouse = Warehouse::factory()->create(['branch_id' => $branch->id]);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $item = $this->createItem('Warehouse Delete Item', $unit);

        $this->createStock($warehouse, $item, 10, 5);

        // Delete warehouse with stock > 0
        $res = $this->deleteJson("/api/v1/inventory/warehouses/{$warehouse->id}");
        $res->assertStatus(422)
            ->assertJsonPath('message', 'No se puede eliminar un almacén con stock disponible.');
    }
}
