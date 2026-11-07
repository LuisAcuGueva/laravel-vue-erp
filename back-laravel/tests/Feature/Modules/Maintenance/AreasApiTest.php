<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Maintenance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Maintenance\Models\Area;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreasApiTest extends TestCase
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

    private function authenticateWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $slug) {
            $perm = Permission::where('slug', $slug)->firstOrFail();
            $user->givePermissionTo($perm);
        }

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    public function test_areas_can_be_created_listed_shown_updated_and_deleted(): void
    {
        $this->authenticateAsAdmin();

        // Create
        $response = $this->postJson('/api/v1/areas', [
            'name' => 'Ventas y Marketing',
            'description' => 'Área de ventas comerciales',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Ventas y Marketing')
            ->assertJsonPath('data.is_active', true);

        $areaId = $response->json('data.id');

        // List
        $listResponse = $this->getJson('/api/v1/areas?search=Marketing');
        $listResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $areaId);

        // Show
        $showResponse = $this->getJson("/api/v1/areas/{$areaId}");
        $showResponse->assertOk()
            ->assertJsonPath('data.id', $areaId)
            ->assertJsonPath('data.name', 'Ventas y Marketing');

        // Update
        $updateResponse = $this->putJson("/api/v1/areas/{$areaId}", [
            'name' => 'Comercial y Ventas',
            'description' => 'Descripción actualizada',
            'is_active' => false,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Comercial y Ventas')
            ->assertJsonPath('data.is_active', false);

        // Delete
        $deleteResponse = $this->deleteJson("/api/v1/areas/{$areaId}");
        $deleteResponse->assertNoContent();

        $this->assertSoftDeleted('areas', ['id' => $areaId]);
    }

    public function test_duplicate_area_name_returns_422(): void
    {
        $this->authenticateAsAdmin();

        Area::factory()->create(['name' => 'Finanzas']);

        $response = $this->postJson('/api/v1/areas', [
            'name' => 'Finanzas',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_reuse_name_of_soft_deleted_area(): void
    {
        $this->authenticateAsAdmin();

        $area = Area::factory()->create(['name' => 'Logística']);
        $area->delete();

        $response = $this->postJson('/api/v1/areas', [
            'name' => 'Logística',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Logística');
    }

    public function test_areas_options_returns_only_active_areas(): void
    {
        $this->authenticateWithPermissions(['areas.view']);

        Area::factory()->create(['name' => 'Área Activa', 'is_active' => true]);
        Area::factory()->create(['name' => 'Área Inactiva', 'is_active' => false]);

        $response = $this->getJson('/api/v1/areas/options');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Área Activa');
    }

    public function test_permissions_are_enforced_on_areas(): void
    {
        $this->authenticateWithPermissions(['areas.view']);

        $area = Area::factory()->create();

        $this->getJson('/api/v1/areas')->assertOk();
        $this->getJson("/api/v1/areas/{$area->id}")->assertOk();

        $this->postJson('/api/v1/areas', ['name' => 'Nueva'])->assertForbidden();
        $this->putJson("/api/v1/areas/{$area->id}", ['name' => 'Edit'])->assertForbidden();
        $this->deleteJson("/api/v1/areas/{$area->id}")->assertForbidden();
    }
}
