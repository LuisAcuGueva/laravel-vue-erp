<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use App\Modules\Inventory\Services\BrandService;
use App\Modules\Inventory\Services\CategoryService;
use App\Modules\Inventory\Services\UnitService;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogItemsApiTest extends TestCase
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

    public function test_catalog_items_crud_with_auto_generated_code_and_base_presentation(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create([
            'unit_category_id' => $unitCategory->id,
            'is_base' => true,
        ]);

        $payload = [
            'name' => 'Laptop Gamer Pro',
            'type' => 'product',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
            'description' => 'Laptop de alto rendimiento',
            'tax_type' => 'taxable',
            'barcode' => '7751234567890',
            'prices' => [
                [
                    'price_type' => 'retail',
                    'price' => 4500.00,
                    'min_quantity' => 1,
                ],
                [
                    'price_type' => 'wholesale',
                    'price' => 4100.00,
                    'min_quantity' => 3,
                ],
            ],
        ];

        $createResponse = $this->postJson('/api/v1/inventory/catalog-items', $payload);
        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Laptop Gamer Pro')
            ->assertJsonPath('data.code', 'ITM-000001')
            ->assertJsonPath('data.track_stock', true);

        $itemId = $createResponse->json('data.id');

        $this->assertDatabaseHas('catalog_items', [
            'id' => $itemId,
            'code' => 'ITM-000001',
            'name' => 'Laptop Gamer Pro',
        ]);

        $this->assertDatabaseHas('item_presentations', [
            'catalog_item_id' => $itemId,
            'unit_id' => $unit->id,
            'barcode' => '7751234567890',
            'is_base' => true,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('item_prices', [
            'price_type' => 'retail',
            'price' => 4500.00,
        ]);

        $showResponse = $this->getJson("/api/v1/inventory/catalog-items/{$itemId}");
        $showResponse->assertOk()
            ->assertJsonPath('data.id', $itemId)
            ->assertJsonPath('data.default_presentation.barcode', '7751234567890');

        $updateResponse = $this->putJson("/api/v1/inventory/catalog-items/{$itemId}", [
            'name' => 'Laptop Gamer Ultra Pro',
            'type' => 'product',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
            'tax_type' => 'taxable',
        ]);
        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Laptop Gamer Ultra Pro');

        $deleteResponse = $this->deleteJson("/api/v1/inventory/catalog-items/{$itemId}");
        $deleteResponse->assertNoContent();

        $this->assertSoftDeleted('catalog_items', ['id' => $itemId]);
    }

    public function test_service_type_forces_track_stock_false(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create([
            'unit_category_id' => $unitCategory->id,
            'is_base' => true,
        ]);

        $payload = [
            'name' => 'Instalación de Sistema',
            'type' => 'service',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'tax_type' => 'taxable',
            'track_stock' => true,
        ];

        $response = $this->postJson('/api/v1/inventory/catalog-items', $payload);
        $response->assertCreated()
            ->assertJsonPath('data.track_stock', false);
    }

    public function test_category_tree_filter_includes_subcategories(): void
    {
        $this->authenticateAsAdmin();

        $parent = Category::factory()->create(['name' => 'Tecnología']);
        $child = Category::factory()->create(['name' => 'Laptops', 'parent_id' => $parent->id]);
        $otherCategory = Category::factory()->create(['name' => 'Muebles']);

        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $itemInChild = CatalogItem::factory()->create([
            'category_id' => $child->id,
            'base_unit_id' => $unit->id,
            'name' => 'Notebook ThinkPad',
        ]);

        $itemOther = CatalogItem::factory()->create([
            'category_id' => $otherCategory->id,
            'base_unit_id' => $unit->id,
            'name' => 'Silla Ergonómica',
        ]);

        $response = $this->getJson("/api/v1/inventory/catalog-items?category_id={$parent->id}");
        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Notebook ThinkPad'));
        $this->assertFalse($names->contains('Silla Ergonómica'));
    }

    public function test_updating_item_without_changing_barcode_does_not_fail_uniqueness(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $createResponse = $this->postJson('/api/v1/inventory/catalog-items', [
            'name' => 'Monitor 24"',
            'type' => 'product',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'tax_type' => 'taxable',
            'barcode' => '7709876543210',
        ]);
        $createResponse->assertCreated();
        $itemId = $createResponse->json('data.id');

        $updateResponse = $this->putJson("/api/v1/inventory/catalog-items/{$itemId}", [
            'name' => 'Monitor 24" LED',
            'type' => 'product',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'tax_type' => 'taxable',
            'barcode' => '7709876543210',
        ]);

        $updateResponse->assertOk();
        $this->assertDatabaseHas('item_presentations', [
            'catalog_item_id' => $itemId,
            'is_base' => true,
            'barcode' => '7709876543210',
        ]);
    }

    public function test_updating_item_barcode_persists_to_base_presentation(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $createResponse = $this->postJson('/api/v1/inventory/catalog-items', [
            'name' => 'Teclado Mecánico',
            'type' => 'product',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'tax_type' => 'taxable',
        ]);
        $createResponse->assertCreated();
        $itemId = $createResponse->json('data.id');

        $updateResponse = $this->putJson("/api/v1/inventory/catalog-items/{$itemId}", [
            'name' => 'Teclado Mecánico',
            'type' => 'product',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'tax_type' => 'taxable',
            'barcode' => '7701111111111',
        ]);

        $updateResponse->assertOk();
        $this->assertDatabaseHas('item_presentations', [
            'catalog_item_id' => $itemId,
            'is_base' => true,
            'barcode' => '7701111111111',
        ]);
    }

    public function test_deleting_item_releases_its_barcode_for_reuse(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $firstItem = $this->postJson('/api/v1/inventory/catalog-items', [
            'name' => 'Mouse Inalámbrico',
            'type' => 'product',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'tax_type' => 'taxable',
            'barcode' => '7705555555555',
        ]);
        $firstItem->assertCreated();
        $firstItemId = $firstItem->json('data.id');

        $this->deleteJson("/api/v1/inventory/catalog-items/{$firstItemId}")->assertNoContent();

        $this->assertSoftDeleted('catalog_items', ['id' => $firstItemId]);
        $this->assertSoftDeleted('item_presentations', [
            'catalog_item_id' => $firstItemId,
            'barcode' => '7705555555555',
        ]);

        $secondItem = $this->postJson('/api/v1/inventory/catalog-items', [
            'name' => 'Mouse Inalámbrico V2',
            'type' => 'product',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'tax_type' => 'taxable',
            'barcode' => '7705555555555',
        ]);

        $secondItem->assertCreated();
        $this->assertDatabaseHas('item_presentations', [
            'catalog_item_id' => $secondItem->json('data.id'),
            'is_base' => true,
            'barcode' => '7705555555555',
            'deleted_at' => null,
        ]);
    }

    public function test_duplicate_code_returns_422(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        CatalogItem::factory()->create([
            'code' => 'ITM-CUSTOM01',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
        ]);

        $response = $this->postJson('/api/v1/inventory/catalog-items', [
            'code' => 'ITM-CUSTOM01',
            'name' => 'Otro producto',
            'type' => 'product',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'tax_type' => 'taxable',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_permissions_are_enforced(): void
    {
        $user = User::factory()->create();
        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $item = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
        ]);

        $this->getJson('/api/v1/inventory/catalog-items')->assertForbidden();
        $this->postJson('/api/v1/inventory/catalog-items', [])->assertForbidden();
        $this->getJson("/api/v1/inventory/catalog-items/{$item->id}")->assertForbidden();
        $this->putJson("/api/v1/inventory/catalog-items/{$item->id}", [])->assertForbidden();
        $this->deleteJson("/api/v1/inventory/catalog-items/{$item->id}")->assertForbidden();
    }

    public function test_cannot_delete_category_brand_or_unit_in_use(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        CatalogItem::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
        ]);

        $this->expectException(InventoryRuleException::class);
        app(CategoryService::class)->delete($category);
    }

    public function test_cannot_delete_brand_in_use(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        CatalogItem::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
        ]);

        $this->expectException(InventoryRuleException::class);
        app(BrandService::class)->delete($brand);
    }

    public function test_cannot_delete_unit_in_use(): void
    {
        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
        ]);

        $this->expectException(InventoryRuleException::class);
        app(UnitService::class)->delete($unit);
    }
}
