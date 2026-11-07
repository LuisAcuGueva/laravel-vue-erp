<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\ItemPrice;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemPricesApiTest extends TestCase
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

    public function test_sync_prices_creates_updates_and_deletes(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $item = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
        ]);

        $presentation = ItemPresentation::factory()->create([
            'catalog_item_id' => $item->id,
            'unit_id' => $unit->id,
            'is_base' => true,
            'is_default' => true,
        ]);

        ItemPrice::factory()->create([
            'item_presentation_id' => $presentation->id,
            'price_type' => 'retail',
            'price' => 50.00,
        ]);

        ItemPrice::factory()->create([
            'item_presentation_id' => $presentation->id,
            'price_type' => 'wholesale',
            'price' => 45.00,
            'min_quantity' => 10,
        ]);

        $payload = [
            'prices' => [
                [
                    'presentation_id' => $presentation->id,
                    'price_type' => 'retail',
                    'price' => 55.00,
                    'min_quantity' => 1,
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/inventory/catalog-items/{$item->id}/prices", $payload);
        $response->assertOk()
            ->assertJsonPath('data.0.price', 55)
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('item_prices', [
            'item_presentation_id' => $presentation->id,
            'price_type' => 'retail',
            'price' => 55.00,
        ]);

        $this->assertDatabaseMissing('item_prices', [
            'item_presentation_id' => $presentation->id,
            'price_type' => 'wholesale',
        ]);
    }

    public function test_sync_rejects_presentation_from_different_item(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $itemA = CatalogItem::factory()->create(['category_id' => $category->id, 'base_unit_id' => $unit->id]);
        $itemB = CatalogItem::factory()->create(['category_id' => $category->id, 'base_unit_id' => $unit->id]);

        $presB = ItemPresentation::factory()->create([
            'catalog_item_id' => $itemB->id,
            'unit_id' => $unit->id,
        ]);

        $payload = [
            'prices' => [
                [
                    'presentation_id' => $presB->id,
                    'price_type' => 'retail',
                    'price' => 99.00,
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/inventory/catalog-items/{$itemA->id}/prices", $payload);
        $response->assertStatus(422)
            ->assertJsonPath('errors.prices.0', 'La presentación indicada no pertenece al ítem.');
    }

    public function test_permission_required_for_updating_prices(): void
    {
        $user = User::factory()->create();
        $perm = Permission::where('slug', 'catalog.view')->firstOrFail();
        $user->givePermissionTo($perm);

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);
        $item = CatalogItem::factory()->create(['category_id' => $category->id, 'base_unit_id' => $unit->id]);

        $this->putJson("/api/v1/inventory/catalog-items/{$item->id}/prices", ['prices' => []])
            ->assertForbidden();
    }
}
