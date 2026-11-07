<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogLookupApiTest extends TestCase
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

    public function test_exact_barcode_match_ranked_first(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $itemA = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz Extra Costeño',
        ]);
        ItemPresentation::factory()->create([
            'catalog_item_id' => $itemA->id,
            'unit_id' => $unit->id,
            'barcode' => '7759999999999',
            'is_base' => true,
        ]);

        $itemB = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz Superior',
        ]);
        ItemPresentation::factory()->create([
            'catalog_item_id' => $itemB->id,
            'unit_id' => $unit->id,
            'barcode' => '7751111111111',
            'is_base' => true,
        ]);

        $response = $this->getJson('/api/v1/inventory/catalog-items/lookup?q=7759999999999&context=sale');
        $response->assertOk()
            ->assertJsonPath('data.0.barcode', '7759999999999')
            ->assertJsonPath('data.0.name', 'Arroz Extra Costeño');
    }

    public function test_context_filtering(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $sellable = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Gaseosa Cola',
            'is_sellable' => true,
            'is_purchasable' => false,
        ]);
        ItemPresentation::factory()->create([
            'catalog_item_id' => $sellable->id,
            'unit_id' => $unit->id,
            'barcode' => '1001',
        ]);

        $purchasableOnly = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Insumo Químico',
            'is_sellable' => false,
            'is_purchasable' => true,
        ]);
        ItemPresentation::factory()->create([
            'catalog_item_id' => $purchasableOnly->id,
            'unit_id' => $unit->id,
            'barcode' => '1002',
        ]);

        $resSale = $this->getJson('/api/v1/inventory/catalog-items/lookup?q=100&context=sale');
        $resSale->assertOk();
        $barcodesSale = collect($resSale->json('data'))->pluck('barcode');
        $this->assertTrue($barcodesSale->contains('1001'));
        $this->assertFalse($barcodesSale->contains('1002'));

        $resPurchase = $this->getJson('/api/v1/inventory/catalog-items/lookup?q=100&context=purchase');
        $resPurchase->assertOk();
        $barcodesPurchase = collect($resPurchase->json('data'))->pluck('barcode');
        $this->assertFalse($barcodesPurchase->contains('1001'));
        $this->assertTrue($barcodesPurchase->contains('1002'));
    }

    public function test_inactive_items_and_presentations_are_excluded(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $inactiveItem = CatalogItem::factory()->inactive()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Producto Desactivado',
        ]);
        ItemPresentation::factory()->create([
            'catalog_item_id' => $inactiveItem->id,
            'unit_id' => $unit->id,
            'barcode' => 'INACTIVE01',
        ]);

        $response = $this->getJson('/api/v1/inventory/catalog-items/lookup?q=INACTIVE01&context=sale');
        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
