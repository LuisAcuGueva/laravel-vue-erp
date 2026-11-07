<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Exceptions\BranchRuleException;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Services\BranchService;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class WarehousesApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        $this->seedUbigeoFixture();
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

    private function authenticateAsRegularUser(array $permissions, array $branchIds = []): User
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

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    public function test_warehouses_crud(): void
    {
        $this->authenticateAsAdmin();
        $branch = Branch::factory()->create();

        // 1. Create
        $response = $this->postJson('/api/v1/inventory/warehouses', [
            'code' => 'ALM-01',
            'name' => 'Almacén Central',
            'branch_id' => $branch->id,
            'address' => 'Av. Los Próceres 123',
            'is_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'ALM-01')
            ->assertJsonPath('data.is_default', true);

        $warehouseId = (string) $response->json('data.id');

        // 2. Retrieve
        $this->getJson("/api/v1/inventory/warehouses/{$warehouseId}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Almacén Central');

        // 3. Update
        $this->putJson("/api/v1/inventory/warehouses/{$warehouseId}", [
            'code' => 'ALM-01',
            'name' => 'Almacén Principal Actualizado',
            'branch_id' => $branch->id,
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Almacén Principal Actualizado');

        // 4. Delete
        $this->deleteJson("/api/v1/inventory/warehouses/{$warehouseId}")
            ->assertStatus(204);

        $this->assertSoftDeleted('warehouses', ['id' => $warehouseId]);
    }

    public function test_only_one_default_warehouse_per_branch(): void
    {
        $this->authenticateAsAdmin();
        $branch = Branch::factory()->create();

        // Create warehouse 1 as default
        $res1 = $this->postJson('/api/v1/inventory/warehouses', [
            'code' => 'W1',
            'name' => 'Almacén 1',
            'branch_id' => $branch->id,
            'is_default' => true,
        ]);
        $res1->assertStatus(201);
        $w1Id = (string) $res1->json('data.id');

        // Create warehouse 2 as default
        $res2 = $this->postJson('/api/v1/inventory/warehouses', [
            'code' => 'W2',
            'name' => 'Almacén 2',
            'branch_id' => $branch->id,
            'is_default' => true,
        ]);
        $res2->assertStatus(201);
        $w2Id = (string) $res2->json('data.id');

        // Warehouse 1 should no longer be default
        $this->assertDatabaseHas('warehouses', ['id' => $w1Id, 'is_default' => false]);
        $this->assertDatabaseHas('warehouses', ['id' => $w2Id, 'is_default' => true]);
    }

    public function test_cannot_create_warehouse_in_inaccessible_branch(): void
    {
        $accessibleBranch = Branch::factory()->create();
        $inaccessibleBranch = Branch::factory()->create();

        $this->authenticateAsRegularUser(
            ['warehouses.create', 'warehouses.view'],
            [$accessibleBranch->id]
        );

        $response = $this->postJson('/api/v1/inventory/warehouses', [
            'code' => 'WH-NO',
            'name' => 'Almacén No Permitido',
            'branch_id' => $inaccessibleBranch->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id']);
    }

    public function test_options_filters_by_active_branch_header(): void
    {
        $branchA = Branch::factory()->create(['name' => 'Sede A']);
        $branchB = Branch::factory()->create(['name' => 'Sede B']);

        $wA = Warehouse::factory()->create(['branch_id' => $branchA->id, 'name' => 'Almacén Sede A']);
        $wB = Warehouse::factory()->create(['branch_id' => $branchB->id, 'name' => 'Almacén Sede B']);

        $this->authenticateAsAdmin();

        // Request with Sede A as active branch
        $resA = $this->withHeader('X-Branch-Id', $branchA->id)
            ->getJson('/api/v1/inventory/warehouses/options');

        $resA->assertStatus(200);
        $idsA = collect($resA->json('data'))->pluck('id')->all();
        $this->assertContains($wA->id, $idsA);
        $this->assertNotContains($wB->id, $idsA);

        // Request with scope=all
        $resAll = $this->withHeader('X-Branch-Id', $branchA->id)
            ->getJson('/api/v1/inventory/warehouses/options?scope=all');

        $resAll->assertStatus(200);
        $idsAll = collect($resAll->json('data'))->pluck('id')->all();
        $this->assertContains($wA->id, $idsAll);
        $this->assertContains($wB->id, $idsAll);
    }

    public function test_cannot_delete_branch_with_registered_warehouses(): void
    {
        $branch = Branch::factory()->create(['is_main' => false]);
        Warehouse::factory()->create(['branch_id' => $branch->id]);

        $this->expectException(BranchRuleException::class);
        $this->expectExceptionMessage('La sede tiene almacenes registrados.');

        app(BranchService::class)->delete($branch);
    }

    public function test_index_lists_only_warehouses_of_accessible_branches(): void
    {
        $mine = Branch::factory()->create();
        $other = Branch::factory()->create();
        $this->authenticateAsRegularUser(['warehouses.view'], [$mine->id]);

        Warehouse::factory()->create(['branch_id' => $mine->id, 'code' => 'ALM01']);
        Warehouse::factory()->create(['branch_id' => $other->id, 'code' => 'ALM02']);

        $this->getJson('/api/v1/inventory/warehouses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'ALM01');
    }

    public function test_index_rejects_branch_filter_of_inaccessible_branch(): void
    {
        $mine = Branch::factory()->create();
        $other = Branch::factory()->create();
        $this->authenticateAsRegularUser(['warehouses.view'], [$mine->id]);

        $this->getJson("/api/v1/inventory/warehouses?branch_id={$other->id}")->assertNotFound();
        $this->getJson("/api/v1/inventory/warehouses?branch_id={$mine->id}")->assertOk();
    }
}
