<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Enums\WarehouseReasonType;
use App\Modules\Inventory\Models\WarehouseReason;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseReasonsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
    }

    private function authenticateAsAdmin(): User
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $adminRole->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    public function test_custom_reasons_crud(): void
    {
        $this->authenticateAsAdmin();

        // 1. Create
        $response = $this->postJson('/api/v1/inventory/warehouse-reasons', [
            'code' => 'MERMA',
            'name' => 'Merma por manipulación',
            'type' => 'out',
            'requires_approval' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'MERMA')
            ->assertJsonPath('data.type', 'out');

        $reasonId = (string) $response->json('data.id');

        // 2. Retrieve
        $this->getJson("/api/v1/inventory/warehouse-reasons/{$reasonId}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Merma por manipulación');

        // 3. Update
        $this->putJson("/api/v1/inventory/warehouse-reasons/{$reasonId}", [
            'code' => 'MERMA',
            'name' => 'Merma por rotura',
            'type' => 'out',
            'requires_approval' => false,
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Merma por rotura')
            ->assertJsonPath('data.requires_approval', false);

        // 4. Delete
        $this->deleteJson("/api/v1/inventory/warehouse-reasons/{$reasonId}")
            ->assertStatus(204);

        $this->assertSoftDeleted('warehouse_reasons', ['id' => $reasonId]);
    }

    public function test_system_reasons_cannot_be_deleted_or_have_code_type_modified(): void
    {
        $this->authenticateAsAdmin();

        $systemReason = WarehouseReason::factory()->system()->create([
            'code' => 'OPENING',
            'name' => 'Inventario inicial',
            'type' => WarehouseReasonType::In,
        ]);

        // Attempt to modify code -> 422
        $this->putJson("/api/v1/inventory/warehouse-reasons/{$systemReason->id}", [
            'code' => 'NEW_CODE',
            'name' => 'Inventario inicial',
            'type' => 'in',
        ])->assertStatus(422)
            ->assertJsonPath('errors.code.0', 'No se puede modificar el código de un motivo de sistema.');

        // Attempt to modify type -> 422
        $this->putJson("/api/v1/inventory/warehouse-reasons/{$systemReason->id}", [
            'code' => 'OPENING',
            'name' => 'Inventario inicial',
            'type' => 'out',
        ])->assertStatus(422)
            ->assertJsonPath('errors.type.0', 'No se puede modificar el tipo de un motivo de sistema.');

        // Attempt to delete -> 422
        $this->deleteJson("/api/v1/inventory/warehouse-reasons/{$systemReason->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.warehouse_reason.0', 'No se puede eliminar un motivo de sistema.');
    }

    public function test_options_excludes_system_reasons_by_default_and_filters_by_type(): void
    {
        $this->authenticateAsAdmin();

        $systemReason = WarehouseReason::factory()->system()->create([
            'code' => 'PURCHASE',
            'name' => 'Compra',
            'type' => WarehouseReasonType::In,
        ]);

        $customReasonIn = WarehouseReason::factory()->create([
            'code' => 'DONATION',
            'name' => 'Donación',
            'type' => WarehouseReasonType::In,
            'is_system' => false,
        ]);

        $customReasonOut = WarehouseReason::factory()->create([
            'code' => 'GIFT',
            'name' => 'Regalo',
            'type' => WarehouseReasonType::Out,
            'is_system' => false,
        ]);

        // Default options (excludes system)
        $resDefault = $this->getJson('/api/v1/inventory/warehouse-reasons/options');
        $resDefault->assertStatus(200);
        $idsDefault = collect($resDefault->json('data'))->pluck('id')->all();
        $this->assertContains($customReasonIn->id, $idsDefault);
        $this->assertContains($customReasonOut->id, $idsDefault);
        $this->assertNotContains($systemReason->id, $idsDefault);

        // Filter by type=in
        $resTypeIn = $this->getJson('/api/v1/inventory/warehouse-reasons/options?type=in');
        $resTypeIn->assertStatus(200);
        $idsTypeIn = collect($resTypeIn->json('data'))->pluck('id')->all();
        $this->assertContains($customReasonIn->id, $idsTypeIn);
        $this->assertNotContains($customReasonOut->id, $idsTypeIn);

        // Include system
        $resWithSystem = $this->getJson('/api/v1/inventory/warehouse-reasons/options?include_system=1');
        $resWithSystem->assertStatus(200);
        $idsWithSystem = collect($resWithSystem->json('data'))->pluck('id')->all();
        $this->assertContains($systemReason->id, $idsWithSystem);
    }
}
