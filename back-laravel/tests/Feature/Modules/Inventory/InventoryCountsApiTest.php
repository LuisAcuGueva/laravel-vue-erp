<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\CatalogItemType;
use App\Modules\Inventory\Enums\InventoryCountStatus;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\WarehouseNoteStatus;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\InventoryCount;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\InventoryMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class InventoryCountsApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private Branch $branch;

    private Warehouse $warehouse;

    private Category $categoryA;

    private Category $categoryB;

    private CatalogItem $productA1;

    private CatalogItem $productA2;

    private CatalogItem $productB1;

    private ItemPresentation $presentationA1;

    private ItemPresentation $presentationA2;

    private ItemPresentation $presentationB1;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        $this->seedUbigeoFixture();
        (new InventoryMastersSeeder)->run();

        $this->branch = Branch::factory()->create([
            'name' => 'Sede Central',
            'code' => 'SEDE-01',
            'ubigeo' => '150101',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Almacén Principal',
            'code' => 'ALM-01',
            'is_active' => true,
        ]);

        $this->categoryA = Category::create([
            'name' => 'Categoría A',
            'code' => 'CAT-A',
            'is_active' => true,
        ]);

        $this->categoryB = Category::create([
            'name' => 'Categoría B',
            'code' => 'CAT-B',
            'is_active' => true,
        ]);

        $unit = Unit::where('symbol', 'UND')->firstOrFail();

        $this->productA1 = CatalogItem::create([
            'category_id' => $this->categoryA->id,
            'base_unit_id' => $unit->id,
            'code' => 'PROD-A1',
            'name' => 'Producto A1',
            'type' => CatalogItemType::Product,
            'track_stock' => true,
            'last_purchase_cost' => 10.0,
            'is_active' => true,
        ]);
        $this->presentationA1 = ItemPresentation::create([
            'catalog_item_id' => $this->productA1->id,
            'unit_id' => $unit->id,
            'name' => 'Unidad',
            'factor' => 1.0,
            'is_base' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->productA2 = CatalogItem::create([
            'category_id' => $this->categoryA->id,
            'base_unit_id' => $unit->id,
            'code' => 'PROD-A2',
            'name' => 'Producto A2',
            'type' => CatalogItemType::Product,
            'track_stock' => true,
            'last_purchase_cost' => 20.0,
            'is_active' => true,
        ]);
        $this->presentationA2 = ItemPresentation::create([
            'catalog_item_id' => $this->productA2->id,
            'unit_id' => $unit->id,
            'name' => 'Unidad',
            'factor' => 1.0,
            'is_base' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->productB1 = CatalogItem::create([
            'category_id' => $this->categoryB->id,
            'base_unit_id' => $unit->id,
            'code' => 'PROD-B1',
            'name' => 'Producto B1',
            'type' => CatalogItemType::Product,
            'track_stock' => true,
            'last_purchase_cost' => 30.0,
            'is_active' => true,
        ]);
        $this->presentationB1 = ItemPresentation::create([
            'catalog_item_id' => $this->productB1->id,
            'unit_id' => $unit->id,
            'name' => 'Unidad',
            'factor' => 1.0,
            'is_base' => true,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function authenticateAsAdmin(): User
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $adminRole->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole($adminRole);
        $user->branches()->sync([$this->branch->id => ['is_default' => true]]);

        $this->withHeader('X-Branch-Id', $this->branch->id);

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    private function seedInitialStock(CatalogItem $item, ItemPresentation $pres, float $qty, float $cost): void
    {
        $ledger = app(StockLedgerService::class);
        $data = new StockMovementData(
            warehouseId: $this->warehouse->id,
            catalogItemId: $item->id,
            baseQuantity: $qty,
            type: MovementType::Opening,
            source: $this->warehouse,
            sourceNumber: 'INI-001',
            unitCost: $cost,
            presentationId: $pres->id,
            presentationQuantity: $qty,
            reasonCode: 'OPENING',
            notes: 'Carga inicial para prueba de toma'
        );
        $ledger->in($data);
    }

    public function test_create_count_populates_items_filtered_by_category(): void
    {
        $this->authenticateAsAdmin();

        $this->seedInitialStock($this->productA1, $this->presentationA1, 10, 15.0);
        $this->seedInitialStock($this->productA2, $this->presentationA2, 5, 25.0);
        $this->seedInitialStock($this->productB1, $this->presentationB1, 20, 35.0);

        // Crear toma filtrando solo categoría A
        $response = $this->postJson('/api/v1/inventory/counts', [
            'warehouse_id' => $this->warehouse->id,
            'category_id' => $this->categoryA->id,
            'count_date' => now()->format('Y-m-d'),
            'notes' => 'Toma parcial de categoría A',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', InventoryCountStatus::Counting->value);

        $number = $response->json('data.number');
        $this->assertStringStartsWith('TI-', $number);

        $countId = $response->json('data.id');
        $count = InventoryCount::with('items')->findOrFail($countId);

        $itemIds = $count->items->pluck('catalog_item_id')->all();
        $this->assertContains($this->productA1->id, $itemIds);
        $this->assertContains($this->productA2->id, $itemIds);
        $this->assertNotContains($this->productB1->id, $itemIds);
    }

    public function test_rejects_second_open_count_in_same_warehouse(): void
    {
        $this->authenticateAsAdmin();

        // 1. Crear primer conteo
        $res1 = $this->postJson('/api/v1/inventory/counts', [
            'warehouse_id' => $this->warehouse->id,
            'count_date' => now()->format('Y-m-d'),
        ]);
        $res1->assertStatus(201);

        // 2. Intentar crear segundo conteo en el mismo almacén
        $res2 = $this->postJson('/api/v1/inventory/counts', [
            'warehouse_id' => $this->warehouse->id,
            'count_date' => now()->format('Y-m-d'),
        ]);

        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse_id']);
    }

    public function test_update_count_items_batch_and_allows_new_items(): void
    {
        $this->authenticateAsAdmin();

        $this->seedInitialStock($this->productA1, $this->presentationA1, 10, 15.0);

        $res = $this->postJson('/api/v1/inventory/counts', [
            'warehouse_id' => $this->warehouse->id,
            'count_date' => now()->format('Y-m-d'),
        ]);
        $countId = $res->json('data.id');

        // Actualizar A1 (ya existente) y agregar A2 (físicamente encontrado, sin stock previo)
        $resUpdate = $this->putJson("/api/v1/inventory/counts/{$countId}/items", [
            'items' => [
                ['catalog_item_id' => $this->productA1->id, 'counted_quantity' => 12],
                ['catalog_item_id' => $this->productA2->id, 'counted_quantity' => 4],
            ],
        ]);

        $resUpdate->assertStatus(200);

        $count = InventoryCount::with('items')->findOrFail($countId);
        $this->assertCount(2, $count->items);

        $itemA1 = $count->items->firstWhere('catalog_item_id', $this->productA1->id);
        $this->assertEquals(12.0, (float) $itemA1->counted_quantity);

        $itemA2 = $count->items->firstWhere('catalog_item_id', $this->productA2->id);
        $this->assertEquals(0.0, (float) $itemA2->system_quantity);
        $this->assertEquals(4.0, (float) $itemA2->counted_quantity);
    }

    public function test_index_reports_counted_items_progress(): void
    {
        $this->authenticateAsAdmin();

        $this->seedInitialStock($this->productA1, $this->presentationA1, 10, 15.0);
        $this->seedInitialStock($this->productA2, $this->presentationA2, 5, 25.0);

        $countId = $this->postJson('/api/v1/inventory/counts', [
            'warehouse_id' => $this->warehouse->id,
            'category_id' => $this->categoryA->id,
            'count_date' => now()->format('Y-m-d'),
        ])->json('data.id');

        $this->putJson("/api/v1/inventory/counts/{$countId}/items", [
            'items' => [['catalog_item_id' => $this->productA1->id, 'counted_quantity' => 10]],
        ])->assertStatus(200);

        $this->getJson('/api/v1/inventory/counts')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $countId)
            ->assertJsonPath('data.0.total_items', 2)
            ->assertJsonPath('data.0.counted_items', 1);
    }

    public function test_close_with_uncounted_items_fails_unless_treat_uncounted_as_zero(): void
    {
        $this->authenticateAsAdmin();

        $this->seedInitialStock($this->productA1, $this->presentationA1, 10, 15.0);
        $this->seedInitialStock($this->productA2, $this->presentationA2, 5, 20.0);

        $res = $this->postJson('/api/v1/inventory/counts', [
            'warehouse_id' => $this->warehouse->id,
            'count_date' => now()->format('Y-m-d'),
        ]);
        $countId = $res->json('data.id');

        // Contamos solo A1
        $this->putJson("/api/v1/inventory/counts/{$countId}/items", [
            'items' => [
                ['catalog_item_id' => $this->productA1->id, 'counted_quantity' => 10],
            ],
        ])->assertStatus(200);

        // Intento de cerrar sin treat_uncounted_as_zero -> 422
        $resCloseFail = $this->postJson("/api/v1/inventory/counts/{$countId}/close", [
            'treat_uncounted_as_zero' => false,
        ]);
        $resCloseFail->assertStatus(422);

        // Cerrar con treat_uncounted_as_zero = true -> 200
        $resCloseOk = $this->postJson("/api/v1/inventory/counts/{$countId}/close", [
            'treat_uncounted_as_zero' => true,
        ]);
        $resCloseOk->assertStatus(200)
            ->assertJsonPath('data.status', InventoryCountStatus::Closed->value);

        $count = InventoryCount::with('items')->findOrFail($countId);
        $itemA2 = $count->items->firstWhere('catalog_item_id', $this->productA2->id);
        $this->assertEquals(0.0, (float) $itemA2->counted_quantity);
    }

    public function test_close_generates_approved_notes_and_matches_counted_stock(): void
    {
        $this->authenticateAsAdmin();

        // Stock inicial: A1 = 10 @ 20.0, A2 = 8 @ 15.0
        $this->seedInitialStock($this->productA1, $this->presentationA1, 10, 20.0);
        $this->seedInitialStock($this->productA2, $this->presentationA2, 8, 15.0);

        $res = $this->postJson('/api/v1/inventory/counts', [
            'warehouse_id' => $this->warehouse->id,
            'count_date' => now()->format('Y-m-d'),
        ]);
        $countId = $res->json('data.id');

        // Físico contado: A1 = 14 (+4 sobrante), A2 = 5 (-3 faltante)
        $this->putJson("/api/v1/inventory/counts/{$countId}/items", [
            'items' => [
                ['catalog_item_id' => $this->productA1->id, 'counted_quantity' => 14],
                ['catalog_item_id' => $this->productA2->id, 'counted_quantity' => 5],
            ],
        ])->assertStatus(200);

        // Cerrar
        $resClose = $this->postJson("/api/v1/inventory/counts/{$countId}/close");
        $resClose->assertStatus(200);

        $count = InventoryCount::with(['warehouseNotes.items'])->findOrFail($countId);
        $this->assertEquals(InventoryCountStatus::Closed, $count->status);

        // Se deben haber generado 2 notas de almacén (1 IN y 1 OUT)
        $this->assertCount(2, $count->warehouseNotes);

        foreach ($count->warehouseNotes as $note) {
            $this->assertEquals(WarehouseNoteStatus::Approved, $note->status);
        }

        // El stock en el almacén debe coincidir EXACTAMENTE con lo contado
        $stockA1 = Stock::where('warehouse_id', $this->warehouse->id)
            ->where('catalog_item_id', $this->productA1->id)
            ->firstOrFail();
        $this->assertEquals(14.0, (float) $stockA1->quantity);

        $stockA2 = Stock::where('warehouse_id', $this->warehouse->id)
            ->where('catalog_item_id', $this->productA2->id)
            ->firstOrFail();
        $this->assertEquals(5.0, (float) $stockA2->quantity);
    }

    public function test_cannot_cancel_count_note_directly(): void
    {
        $this->authenticateAsAdmin();

        $this->seedInitialStock($this->productA1, $this->presentationA1, 10, 20.0);

        $res = $this->postJson('/api/v1/inventory/counts', [
            'warehouse_id' => $this->warehouse->id,
            'count_date' => now()->format('Y-m-d'),
        ]);
        $countId = $res->json('data.id');

        $this->putJson("/api/v1/inventory/counts/{$countId}/items", [
            'items' => [
                ['catalog_item_id' => $this->productA1->id, 'counted_quantity' => 15],
            ],
        ])->assertStatus(200);

        $this->postJson("/api/v1/inventory/counts/{$countId}/close")->assertStatus(200);

        $count = InventoryCount::with('warehouseNotes')->findOrFail($countId);
        $note = $count->warehouseNotes->firstOrFail();

        // Intentar anular la nota generada directamente a través de /warehouse-notes/{id}/cancel
        $resCancel = $this->postJson("/api/v1/inventory/warehouse-notes/{$note->id}/cancel", [
            'reason' => 'Intento de anulación directa de nota de ajuste',
        ]);

        $resCancel->assertStatus(422);
    }

    public function test_cancel_closed_count_reverses_notes_and_restores_stock(): void
    {
        $this->authenticateAsAdmin();

        $this->seedInitialStock($this->productA1, $this->presentationA1, 10, 20.0);

        $res = $this->postJson('/api/v1/inventory/counts', [
            'warehouse_id' => $this->warehouse->id,
            'count_date' => now()->format('Y-m-d'),
        ]);
        $countId = $res->json('data.id');

        $this->putJson("/api/v1/inventory/counts/{$countId}/items", [
            'items' => [
                ['catalog_item_id' => $this->productA1->id, 'counted_quantity' => 15],
            ],
        ])->assertStatus(200);

        $this->postJson("/api/v1/inventory/counts/{$countId}/close")->assertStatus(200);

        // Stock tras cierre es 15
        $stock = Stock::where('warehouse_id', $this->warehouse->id)
            ->where('catalog_item_id', $this->productA1->id)
            ->firstOrFail();
        $this->assertEquals(15.0, (float) $stock->quantity);

        // Anular la toma de inventario
        $resCancel = $this->postJson("/api/v1/inventory/counts/{$countId}/cancel");
        $resCancel->assertStatus(200)
            ->assertJsonPath('data.status', InventoryCountStatus::Cancelled->value);

        // Stock debe haber vuelto a su valor inicial: 10
        $stock->refresh();
        $this->assertEquals(10.0, (float) $stock->quantity);
    }

    public function test_verify_stock_command_runs_clean(): void
    {
        $this->authenticateAsAdmin();

        $this->seedInitialStock($this->productA1, $this->presentationA1, 20, 10.0);

        $exitCode = Artisan::call('inventory:verify-stock');
        $this->assertSame(0, $exitCode);
    }
}
