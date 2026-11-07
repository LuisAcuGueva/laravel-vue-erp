<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Models\Category;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriesApiTest extends TestCase
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

    public function test_categories_can_be_created_retrieved_updated_and_deleted(): void
    {
        $this->authenticateAsAdmin();

        // 1. Create root category
        $response = $this->postJson('/api/v1/inventory/categories', [
            'name' => 'Frenos',
            'code' => 'FRE',
            'description' => 'Sistema de frenos',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Frenos')
            ->assertJsonPath('data.code', 'FRE')
            ->assertJsonPath('data.depth', 0);

        $categoryId = (string) $response->json('data.id');

        // 2. Retrieve
        $this->getJson("/api/v1/inventory/categories/{$categoryId}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Frenos');

        // 3. Update
        $this->putJson("/api/v1/inventory/categories/{$categoryId}", [
            'name' => 'Frenos y Discos',
            'code' => 'FRE',
            'description' => 'Sistema de frenos y discos',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Frenos y Discos');

        // 4. Delete
        $this->deleteJson("/api/v1/inventory/categories/{$categoryId}")
            ->assertStatus(204);

        $this->assertSoftDeleted('categories', ['id' => $categoryId]);
    }

    public function test_max_depth_of_three_levels_enforced(): void
    {
        $this->authenticateAsAdmin();

        // Level 1 (depth 0)
        $level1 = Category::factory()->create(['name' => 'L1', 'depth' => 0, 'parent_id' => null]);

        // Level 2 (depth 1)
        $level2 = Category::factory()->create(['name' => 'L2', 'depth' => 1, 'parent_id' => $level1->id]);

        // Level 3 (depth 2)
        $level3 = Category::factory()->create(['name' => 'L3', 'depth' => 2, 'parent_id' => $level2->id]);

        // Level 4 (depth 3) -> should fail with 422
        $response = $this->postJson('/api/v1/inventory/categories', [
            'name' => 'L4',
            'parent_id' => $level3->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_cycles_are_prevented_on_update(): void
    {
        $this->authenticateAsAdmin();

        $c1 = Category::factory()->create(['name' => 'C1', 'depth' => 0, 'parent_id' => null]);
        $c2 = Category::factory()->create(['name' => 'C2', 'depth' => 1, 'parent_id' => $c1->id]);
        $c3 = Category::factory()->create(['name' => 'C3', 'depth' => 2, 'parent_id' => $c2->id]);

        // Setting parent to itself
        $this->putJson("/api/v1/inventory/categories/{$c1->id}", [
            'name' => 'C1',
            'parent_id' => $c1->id,
        ])->assertStatus(422);

        // Setting parent to a descendant (c1 parent -> c3)
        $this->putJson("/api/v1/inventory/categories/{$c1->id}", [
            'name' => 'C1',
            'parent_id' => $c3->id,
        ])->assertStatus(422);
    }

    public function test_duplicate_name_under_same_parent_fails_but_allowed_in_different_branches(): void
    {
        $this->authenticateAsAdmin();

        $parentA = Category::factory()->create(['name' => 'Parent A', 'depth' => 0]);
        $parentB = Category::factory()->create(['name' => 'Parent B', 'depth' => 0]);

        // Create in parent A
        $this->postJson('/api/v1/inventory/categories', [
            'name' => 'Repuestos',
            'parent_id' => $parentA->id,
        ])->assertStatus(201);

        // Duplicate in parent A -> 422
        $this->postJson('/api/v1/inventory/categories', [
            'name' => 'Repuestos',
            'parent_id' => $parentA->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        // Same name in parent B -> Allowed (201)
        $this->postJson('/api/v1/inventory/categories', [
            'name' => 'Repuestos',
            'parent_id' => $parentB->id,
        ])->assertStatus(201);
    }

    public function test_tree_returns_hierarchical_structure(): void
    {
        $this->authenticateAsAdmin();

        $root = Category::factory()->create(['name' => 'Motor', 'depth' => 0, 'parent_id' => null]);
        $child = Category::factory()->create(['name' => 'Pistones', 'depth' => 1, 'parent_id' => $root->id]);

        $response = $this->getJson('/api/v1/inventory/categories/tree');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'depth',
                        'children',
                    ],
                ],
            ]);

        $items = $response->json('data');
        $this->assertTrue(collect($items)->contains('name', 'Motor'));
    }

    public function test_cannot_delete_category_with_subcategories(): void
    {
        $this->authenticateAsAdmin();

        $root = Category::factory()->create(['name' => 'Electricidad', 'depth' => 0, 'parent_id' => null]);
        Category::factory()->create(['name' => 'Baterías', 'depth' => 1, 'parent_id' => $root->id]);

        $this->deleteJson("/api/v1/inventory/categories/{$root->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.category.0', 'No se puede eliminar una categoría que tiene subcategorías asociadas.');
    }

    public function test_permissions_are_enforced_on_categories(): void
    {
        // Without permission
        $this->authenticateWithPermissions([]);
        $this->getJson('/api/v1/inventory/categories')->assertStatus(403);
        $this->postJson('/api/v1/inventory/categories', ['name' => 'X'])->assertStatus(403);

        // With view permission
        $this->authenticateWithPermissions(['categories.view']);
        $this->getJson('/api/v1/inventory/categories')->assertStatus(200);
        $this->postJson('/api/v1/inventory/categories', ['name' => 'X'])->assertStatus(403);
    }

    public function test_index_orders_hierarchically_and_exposes_path(): void
    {
        $this->authenticateAsAdmin();

        $food = Category::factory()->create(['name' => 'Alimentos', 'depth' => 0]);
        $tech = Category::factory()->create(['name' => 'Tecnología', 'depth' => 0]);
        $computers = Category::factory()->create(['name' => 'Cómputo', 'parent_id' => $tech->id, 'depth' => 1]);
        Category::factory()->create(['name' => 'Laptops', 'parent_id' => $computers->id, 'depth' => 2]);
        Category::factory()->create(['name' => 'Abarrotes', 'parent_id' => $food->id, 'depth' => 1]);

        $response = $this->getJson('/api/v1/inventory/categories')->assertOk();

        $this->assertSame(
            ['Alimentos', 'Abarrotes', 'Tecnología', 'Cómputo', 'Laptops'],
            array_column($response->json('data'), 'name'),
        );
        $response->assertJsonPath('data.4.path', 'Tecnología › Cómputo › Laptops');
    }

    public function test_index_filters_by_depth_and_rejects_invalid_parent(): void
    {
        $this->authenticateAsAdmin();

        $tech = Category::factory()->create(['name' => 'Tecnología', 'depth' => 0]);
        Category::factory()->create(['name' => 'Cómputo', 'parent_id' => $tech->id, 'depth' => 1]);

        $this->getJson('/api/v1/inventory/categories?depth=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Cómputo');

        $this->getJson('/api/v1/inventory/categories?parent_id=no-es-uuid')->assertStatus(422);
    }
}
