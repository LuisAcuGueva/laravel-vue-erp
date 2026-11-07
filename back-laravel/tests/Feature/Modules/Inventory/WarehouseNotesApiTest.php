<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Enums\CatalogItemType;
use App\Modules\Inventory\Enums\SystemReason;
use App\Modules\Inventory\Enums\WarehouseNoteStatus;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseNote;
use App\Modules\Inventory\Models\WarehouseReason;
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

class WarehouseNotesApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private Branch $branchA;

    private Branch $branchB;

    private Warehouse $warehouseA1;

    private Warehouse $warehouseA2;

    private Warehouse $warehouseB1;

    private CatalogItem $product;

    private ItemPresentation $presentation;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        $this->seedUbigeoFixture();
        (new InventoryMastersSeeder)->run();

        $this->branchA = Branch::factory()->create([
            'name' => 'Sede Principal',
            'code' => 'SEDE-A',
            'ubigeo' => '150101',
            'is_active' => true,
        ]);

        $this->branchB = Branch::factory()->create([
            'name' => 'Sede Sucursal',
            'code' => 'SEDE-B',
            'ubigeo' => '150101',
            'is_active' => true,
        ]);

        $this->warehouseA1 = Warehouse::factory()->create([
            'branch_id' => $this->branchA->id,
            'name' => 'Almacén Central A1',
            'code' => 'ALM-A1',
            'is_active' => true,
        ]);

        $this->warehouseA2 = Warehouse::factory()->create([
            'branch_id' => $this->branchA->id,
            'name' => 'Almacén Secundario A2',
            'code' => 'ALM-A2',
            'is_active' => true,
        ]);

        $this->warehouseB1 = Warehouse::factory()->create([
            'branch_id' => $this->branchB->id,
            'name' => 'Almacén Sucursal B1',
            'code' => 'ALM-B1',
            'is_active' => true,
        ]);

        $category = Category::where('name', 'General')->first() ?? Category::factory()->create(['name' => 'General']);
        $unitCategory = UnitCategory::firstOrCreate(['name' => 'Unidades'], ['description' => 'Unidades']);
        $unit = Unit::where('symbol', 'UND')->firstOrFail();

        $this->product = CatalogItem::create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'code' => 'PROD-001',
            'name' => 'Producto Prueba',
            'type' => CatalogItemType::Product,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $this->presentation = ItemPresentation::create([
            'catalog_item_id' => $this->product->id,
            'unit_id' => $unit->id,
            'name' => 'Unidad',
            'factor' => 1.0,
            'is_base' => true,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function authenticateAsAdmin(?Branch $branch = null): User
    {
        $branch ??= $this->branchA;
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $adminRole->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole($adminRole);
        $user->branches()->sync([
            $this->branchA->id => ['is_default' => true],
            $this->branchB->id => ['is_default' => false],
        ]);

        $this->withHeader('X-Branch-Id', $branch->id);

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    private function authenticateUserWithPermissions(array $permissions, array $branchIds = [], ?string $activeBranchId = null): User
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

    public function test_can_create_draft_note_without_affecting_stock(): void
    {
        $this->authenticateAsAdmin();

        $reason = WarehouseReason::where('code', 'DONATION_IN')->firstOrFail();

        $payload = [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reason->id,
            'document_date' => now()->format('Y-m-d'),
            'reference_document' => 'DOC-001',
            'observation' => 'Nota de donación de prueba',
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 10,
                    'unit_cost' => 25.50,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/inventory/warehouse-notes', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', WarehouseNoteStatus::Draft->value)
            ->assertJsonPath('data.type', 'in');

        $this->assertEquals(255.0, (float) $response->json('data.total_cost'));

        // Stock no debe verse afectado
        $stock = Stock::where('warehouse_id', $this->warehouseA1->id)
            ->where('catalog_item_id', $this->product->id)
            ->first();

        $this->assertNull($stock);
    }

    public function test_sequential_number_generation_per_type(): void
    {
        $this->authenticateAsAdmin();

        $reasonIn = WarehouseReason::where('code', 'DONATION_IN')->firstOrFail();
        $reasonOut = WarehouseReason::where('code', 'SHRINKAGE')->firstOrFail();
        $reasonTransfer = WarehouseReason::where('code', SystemReason::Transfer->value)->firstOrFail();

        // Crear IN
        $resIn = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonIn->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 5, 'unit_cost' => 10.0],
            ],
        ]);
        $resIn->assertStatus(201);
        $this->assertStringStartsWith('NI-', $resIn->json('data.number'));

        // Crear OUT
        $resOut = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'out',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonOut->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 2],
            ],
        ]);
        $resOut->assertStatus(201);
        $this->assertStringStartsWith('NS-', $resOut->json('data.number'));

        // Crear TRANSFER
        $resTransfer = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'transfer',
            'warehouse_id' => $this->warehouseA1->id,
            'target_warehouse_id' => $this->warehouseA2->id,
            'reason_id' => $reasonTransfer->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 1],
            ],
        ]);
        $resTransfer->assertStatus(201);
        $this->assertStringStartsWith('NT-', $resTransfer->json('data.number'));
    }

    public function test_rejects_incompatible_reason_for_note_type(): void
    {
        $this->authenticateAsAdmin();

        $reasonOut = WarehouseReason::where('code', 'SHRINKAGE')->firstOrFail();

        // Intentar registrar un IN con un motivo OUT
        $response = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonOut->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 5, 'unit_cost' => 10.0],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason_id']);
    }

    public function test_rejects_system_reasons_except_allowed_ones(): void
    {
        $this->authenticateAsAdmin();

        $saleReason = WarehouseReason::where('code', SystemReason::Sale->value)->firstOrFail();

        // Intento con SALE (motivo de sistema de ventas)
        $response = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'out',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $saleReason->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);

        // Motivo OPENING en IN sí debe estar permitido
        $openingReason = WarehouseReason::where('code', SystemReason::Opening->value)->firstOrFail();
        $resOpening = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $openingReason->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 10, 'unit_cost' => 12.0],
            ],
        ]);
        $resOpening->assertStatus(201);
    }

    public function test_rejects_non_stockable_or_inactive_items(): void
    {
        $this->authenticateAsAdmin();

        $unit = Unit::where('sunat_code', 'NIU')->firstOrFail();
        $service = CatalogItem::create([
            'category_id' => $this->product->category_id,
            'base_unit_id' => $unit->id,
            'code' => 'SRV-001',
            'name' => 'Servicio de Mantenimiento',
            'type' => CatalogItemType::Service,
            'track_stock' => false,
            'is_active' => true,
        ]);

        $servicePresentation = ItemPresentation::create([
            'catalog_item_id' => $service->id,
            'unit_id' => $unit->id,
            'name' => 'Servicio',
            'factor' => 1.0,
            'is_base' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $reason = WarehouseReason::where('code', 'DONATION_IN')->firstOrFail();

        $response = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reason->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $servicePresentation->id, 'quantity' => 1, 'unit_cost' => 10.0],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_transfer_to_same_warehouse(): void
    {
        $this->authenticateAsAdmin();

        $reasonTransfer = WarehouseReason::where('code', SystemReason::Transfer->value)->firstOrFail();

        $response = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'transfer',
            'warehouse_id' => $this->warehouseA1->id,
            'target_warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonTransfer->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_warehouse_id']);
    }

    public function test_cannot_update_approved_note(): void
    {
        $this->authenticateAsAdmin();

        $reasonIn = WarehouseReason::where('code', 'DONATION_IN')->firstOrFail();

        $res = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonIn->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 5, 'unit_cost' => 10.0],
            ],
        ]);
        $res->assertStatus(201);
        $noteId = $res->json('data.id');

        // Aprobar
        $this->postJson("/api/v1/inventory/warehouse-notes/{$noteId}/approve")
            ->assertStatus(200);

        // Intentar actualizar la nota aprobada
        $resUpdate = $this->putJson("/api/v1/inventory/warehouse-notes/{$noteId}", [
            'reason_id' => $reasonIn->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 8, 'unit_cost' => 12.0],
            ],
        ]);

        $resUpdate->assertStatus(422);
    }

    public function test_approve_in_note_updates_stock_and_average_cost(): void
    {
        $this->authenticateAsAdmin();

        $reasonIn = WarehouseReason::where('code', 'DONATION_IN')->firstOrFail();

        // Ingreso 1: 10 unidades @ 20.00
        $res1 = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonIn->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 10, 'unit_cost' => 20.0],
            ],
        ]);
        $noteId1 = $res1->json('data.id');
        $this->postJson("/api/v1/inventory/warehouse-notes/{$noteId1}/approve")->assertStatus(200);

        $stock = Stock::where('warehouse_id', $this->warehouseA1->id)
            ->where('catalog_item_id', $this->product->id)
            ->firstOrFail();

        $this->assertEquals(10.0, (float) $stock->quantity);
        $this->assertEquals(20.0, (float) $stock->avg_cost);

        // Ingreso 2: 10 unidades @ 30.00 -> Promedio ponderado: (10*20 + 10*30) / 20 = 25.00
        $res2 = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonIn->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 10, 'unit_cost' => 30.0],
            ],
        ]);
        $noteId2 = $res2->json('data.id');
        $this->postJson("/api/v1/inventory/warehouse-notes/{$noteId2}/approve")->assertStatus(200);

        $stock->refresh();
        $this->assertEquals(20.0, (float) $stock->quantity);
        $this->assertEquals(25.0, (float) $stock->avg_cost);
    }

    public function test_approve_out_note_with_insufficient_stock_fails_and_remains_draft(): void
    {
        $this->authenticateAsAdmin();

        $reasonOut = WarehouseReason::where('code', 'SHRINKAGE')->firstOrFail();

        // Crear salida por 5 unidades cuando stock es 0
        $resOut = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'out',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonOut->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 5],
            ],
        ]);
        $resOut->assertStatus(201);
        $noteId = $resOut->json('data.id');

        // Aprobar debe fallar con 422
        $resApprove = $this->postJson("/api/v1/inventory/warehouse-notes/{$noteId}/approve");
        $resApprove->assertStatus(422);

        // La nota debe seguir en borrador
        $note = WarehouseNote::findOrFail($noteId);
        $this->assertTrue($note->isDraft());
    }

    public function test_approve_transfer_between_branches(): void
    {
        $this->authenticateAsAdmin();

        // 1. Cargar stock en warehouseA1 (Sede A)
        $reasonIn = WarehouseReason::where('code', 'DONATION_IN')->firstOrFail();
        $resIn = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonIn->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 20, 'unit_cost' => 50.0],
            ],
        ]);
        $noteInId = $resIn->json('data.id');
        $this->postJson("/api/v1/inventory/warehouse-notes/{$noteInId}/approve")->assertStatus(200);

        // 2. Crear y aprobar traslado de warehouseA1 hacia warehouseB1 (Sede B)
        $reasonTransfer = WarehouseReason::where('code', SystemReason::Transfer->value)->firstOrFail();
        $resTransfer = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'transfer',
            'warehouse_id' => $this->warehouseA1->id,
            'target_warehouse_id' => $this->warehouseB1->id,
            'reason_id' => $reasonTransfer->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 8],
            ],
        ]);
        $resTransfer->assertStatus(201);
        $transferId = $resTransfer->json('data.id');

        $this->postJson("/api/v1/inventory/warehouse-notes/{$transferId}/approve")->assertStatus(200);

        // Verificar stock de origen (20 - 8 = 12)
        $stockOrigin = Stock::where('warehouse_id', $this->warehouseA1->id)
            ->where('catalog_item_id', $this->product->id)
            ->firstOrFail();
        $this->assertEquals(12.0, (float) $stockOrigin->quantity);

        // Verificar stock de destino (0 + 8 = 8, costo 50.0)
        $stockTarget = Stock::where('warehouse_id', $this->warehouseB1->id)
            ->where('catalog_item_id', $this->product->id)
            ->firstOrFail();
        $this->assertEquals(8.0, (float) $stockTarget->quantity);
        $this->assertEquals(50.0, (float) $stockTarget->avg_cost);
    }

    public function test_approve_true_behavior_depending_on_reason_requires_approval(): void
    {
        $this->authenticateAsAdmin();

        // 1. Motivo con requires_approval = false (SUPPLIER_BONUS)
        $reasonNoApproval = WarehouseReason::where('code', 'SUPPLIER_BONUS')->firstOrFail();
        $this->assertFalse((bool) $reasonNoApproval->requires_approval);

        $res1 = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonNoApproval->id,
            'document_date' => now()->format('Y-m-d'),
            'approve' => true,
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 5, 'unit_cost' => 15.0],
            ],
        ]);

        $res1->assertStatus(201)
            ->assertJsonPath('data.status', WarehouseNoteStatus::Approved->value);

        // 2. Motivo con requires_approval = true (DONATION_IN)
        $reasonWithApproval = WarehouseReason::where('code', 'DONATION_IN')->firstOrFail();
        $this->assertTrue((bool) $reasonWithApproval->requires_approval);

        $res2 = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonWithApproval->id,
            'document_date' => now()->format('Y-m-d'),
            'approve' => true,
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 5, 'unit_cost' => 15.0],
            ],
        ]);

        $res2->assertStatus(201)
            ->assertJsonPath('data.status', WarehouseNoteStatus::Draft->value)
            ->assertJsonPath('meta.approval_pending', true);
    }

    public function test_cancel_approved_note_reverses_kardex_and_stock(): void
    {
        $this->authenticateAsAdmin();

        $reasonIn = WarehouseReason::where('code', 'DONATION_IN')->firstOrFail();

        $resIn = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonIn->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 15, 'unit_cost' => 40.0],
            ],
        ]);
        $noteId = $resIn->json('data.id');
        $this->postJson("/api/v1/inventory/warehouse-notes/{$noteId}/approve")->assertStatus(200);

        $stock = Stock::where('warehouse_id', $this->warehouseA1->id)
            ->where('catalog_item_id', $this->product->id)
            ->firstOrFail();
        $this->assertEquals(15.0, (float) $stock->quantity);

        // Anular nota
        $resCancel = $this->postJson("/api/v1/inventory/warehouse-notes/{$noteId}/cancel", [
            'reason' => 'Error en el documento de donación recibido',
        ]);

        $resCancel->assertStatus(200)
            ->assertJsonPath('data.status', WarehouseNoteStatus::Cancelled->value);

        $stock->refresh();
        $this->assertEquals(0.0, (float) $stock->quantity);
    }

    public function test_cancel_in_note_fails_if_stock_already_consumed(): void
    {
        $this->authenticateAsAdmin();

        $reasonIn = WarehouseReason::where('code', 'DONATION_IN')->firstOrFail();
        $reasonOut = WarehouseReason::where('code', 'SHRINKAGE')->firstOrFail();

        // 1. Ingreso de 10 unidades
        $resIn = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonIn->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 10, 'unit_cost' => 10.0],
            ],
        ]);
        $noteInId = $resIn->json('data.id');
        $this->postJson("/api/v1/inventory/warehouse-notes/{$noteInId}/approve")->assertStatus(200);

        // 2. Salida de 7 unidades (quedan 3)
        $resOut = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'out',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonOut->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 7],
            ],
        ]);
        $noteOutId = $resOut->json('data.id');
        $this->postJson("/api/v1/inventory/warehouse-notes/{$noteOutId}/approve")->assertStatus(200);

        // 3. Intentar anular la nota de ingreso (que revertiría 10 unidades cuando solo hay 3)
        $resCancel = $this->postJson("/api/v1/inventory/warehouse-notes/{$noteInId}/cancel", [
            'reason' => 'Intento de anulación de ingreso ya consumido',
        ]);

        $resCancel->assertStatus(422);

        $noteIn = WarehouseNote::findOrFail($noteInId);
        $this->assertTrue($noteIn->isApproved());
    }

    public function test_transfer_visibility_in_destination_branch(): void
    {
        // Administrador crea traslado desde Sede A hacia Sede B
        $this->authenticateAsAdmin($this->branchA);

        $reasonTransfer = WarehouseReason::where('code', SystemReason::Transfer->value)->firstOrFail();
        $resTransfer = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'transfer',
            'warehouse_id' => $this->warehouseA1->id,
            'target_warehouse_id' => $this->warehouseB1->id,
            'reason_id' => $reasonTransfer->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 3],
            ],
        ]);
        $noteId = $resTransfer->json('data.id');

        // Usuario con acceso únicamente a Sede B
        $this->authenticateUserWithPermissions(
            ['warehouse_notes.view'],
            [$this->branchB->id],
            $this->branchB->id
        );

        // Debe poder consultar el detalle de la nota por ser el almacén de destino
        $resShow = $this->getJson("/api/v1/inventory/warehouse-notes/{$noteId}");
        $resShow->assertStatus(200)
            ->assertJsonPath('data.id', $noteId);

        // Debe figurar en el listado de Sede B
        $resIndex = $this->getJson('/api/v1/inventory/warehouse-notes');
        $resIndex->assertStatus(200);
        $ids = collect($resIndex->json('data'))->pluck('id')->all();
        $this->assertContains($noteId, $ids);
    }

    public function test_unauthorized_user_cannot_access(): void
    {
        $this->authenticateUserWithPermissions([], [$this->branchA->id], $this->branchA->id);

        $this->getJson('/api/v1/inventory/warehouse-notes')->assertStatus(403);
        $this->postJson('/api/v1/inventory/warehouse-notes', [])->assertStatus(403);
    }

    public function test_verify_stock_command_runs_clean(): void
    {
        $this->authenticateAsAdmin();

        // Creamos y aprobamos varios movimientos
        $reasonIn = WarehouseReason::where('code', 'DONATION_IN')->firstOrFail();
        $resIn = $this->postJson('/api/v1/inventory/warehouse-notes', [
            'type' => 'in',
            'warehouse_id' => $this->warehouseA1->id,
            'reason_id' => $reasonIn->id,
            'document_date' => now()->format('Y-m-d'),
            'items' => [
                ['item_presentation_id' => $this->presentation->id, 'quantity' => 20, 'unit_cost' => 10.0],
            ],
        ]);
        $this->postJson('/api/v1/inventory/warehouse-notes/'.$resIn->json('data.id').'/approve')->assertStatus(200);

        $exitCode = Artisan::call('inventory:verify-stock');
        $this->assertSame(0, $exitCode);
    }
}
