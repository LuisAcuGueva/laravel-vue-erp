<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandsApiTest extends TestCase
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

    public function test_brands_can_be_created_retrieved_updated_and_deleted(): void
    {
        $this->authenticateAsAdmin();

        // 1. Create
        $response = $this->postJson('/api/v1/inventory/brands', [
            'name' => 'Bosch',
            'description' => 'Fabricante alemán de autopartes',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Bosch');

        $brandId = (string) $response->json('data.id');

        // 2. Retrieve
        $this->getJson("/api/v1/inventory/brands/{$brandId}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Bosch');

        // 3. Update
        $this->putJson("/api/v1/inventory/brands/{$brandId}", [
            'name' => 'Bosch Global',
            'description' => 'Actualizado',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Bosch Global');

        // 4. Delete
        $this->deleteJson("/api/v1/inventory/brands/{$brandId}")
            ->assertStatus(204);

        $this->assertSoftDeleted('brands', ['id' => $brandId]);
    }

    public function test_duplicate_brand_name_returns_422(): void
    {
        $this->authenticateAsAdmin();

        Brand::factory()->create(['name' => 'Denso']);

        $response = $this->postJson('/api/v1/inventory/brands', [
            'name' => 'Denso',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_reuse_name_of_soft_deleted_brand(): void
    {
        $this->authenticateAsAdmin();

        $brand = Brand::factory()->create(['name' => 'Brembo']);
        $brand->delete();

        $response = $this->postJson('/api/v1/inventory/brands', [
            'name' => 'Brembo',
        ]);

        $response->assertStatus(201);
    }

    public function test_brand_options_returns_only_active_brands(): void
    {
        $this->authenticateAsAdmin();

        Brand::factory()->create(['name' => 'Active Brand', 'is_active' => true]);
        Brand::factory()->inactive()->create(['name' => 'Inactive Brand']);

        $response = $this->getJson('/api/v1/inventory/brands/options');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertContains('Active Brand', $names);
        $this->assertNotContains('Inactive Brand', $names);
    }

    public function test_permissions_are_enforced_on_brands(): void
    {
        $this->authenticateWithPermissions([]);
        $this->getJson('/api/v1/inventory/brands')->assertStatus(403);
        $this->postJson('/api/v1/inventory/brands', ['name' => 'Test'])->assertStatus(403);

        $this->authenticateWithPermissions(['brands.view']);
        $this->getJson('/api/v1/inventory/brands')->assertStatus(200);
        $this->postJson('/api/v1/inventory/brands', ['name' => 'Test'])->assertStatus(403);
    }
}
