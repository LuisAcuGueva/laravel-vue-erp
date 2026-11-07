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

class ItemPresentationsApiTest extends TestCase
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

    public function test_can_add_presentation_with_factor(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $baseUnit = Unit::factory()->create([
            'unit_category_id' => $unitCategory->id,
            'name' => 'Unidad',
            'symbol' => 'UND',
            'is_base' => true,
            'allow_decimals' => false,
        ]);
        $boxUnit = Unit::factory()->create([
            'unit_category_id' => $unitCategory->id,
            'name' => 'Docena',
            'symbol' => 'DOC',
            'is_base' => false,
            'allow_decimals' => false,
        ]);

        $item = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $baseUnit->id,
        ]);

        $basePres = ItemPresentation::factory()->create([
            'catalog_item_id' => $item->id,
            'unit_id' => $baseUnit->id,
            'is_base' => true,
            'is_default' => true,
        ]);

        $response = $this->postJson("/api/v1/inventory/catalog-items/{$item->id}/presentations", [
            'name' => 'Caja x 12 unidades',
            'unit_id' => $boxUnit->id,
            'factor' => 12.0000,
            'barcode' => '7759998887776',
            'is_default' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Caja x 12 unidades')
            ->assertJsonPath('data.factor', 12)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('item_presentations', [
            'id' => $basePres->id,
            'is_default' => false,
        ]);
    }

    public function test_factor_must_be_integer_if_base_unit_does_not_allow_decimals(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $baseUnit = Unit::factory()->create([
            'unit_category_id' => $unitCategory->id,
            'is_base' => true,
            'allow_decimals' => false,
        ]);
        $derivedUnit = Unit::factory()->create([
            'unit_category_id' => $unitCategory->id,
            'is_base' => false,
            'allow_decimals' => true,
        ]);

        $item = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $baseUnit->id,
        ]);

        ItemPresentation::factory()->create([
            'catalog_item_id' => $item->id,
            'unit_id' => $baseUnit->id,
            'is_base' => true,
            'is_default' => true,
        ]);

        $response = $this->postJson("/api/v1/inventory/catalog-items/{$item->id}/presentations", [
            'name' => 'Fracción',
            'unit_id' => $derivedUnit->id,
            'factor' => 2.5,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.factor.0', 'La unidad base no permite decimales; el factor debe ser un número entero.');
    }

    public function test_duplicate_barcode_returns_422(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $item = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
        ]);

        ItemPresentation::factory()->create([
            'barcode' => '7750000000001',
        ]);

        $response = $this->postJson("/api/v1/inventory/catalog-items/{$item->id}/presentations", [
            'name' => 'Nueva presentación',
            'unit_id' => $unit->id,
            'factor' => 1,
            'barcode' => '7750000000001',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['barcode']);
    }

    public function test_cannot_delete_base_presentation(): void
    {
        $this->authenticateAsAdmin();

        $category = Category::factory()->create();
        $unitCategory = UnitCategory::factory()->create();
        $unit = Unit::factory()->create(['unit_category_id' => $unitCategory->id, 'is_base' => true]);

        $item = CatalogItem::factory()->create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
        ]);

        $basePres = ItemPresentation::factory()->create([
            'catalog_item_id' => $item->id,
            'unit_id' => $unit->id,
            'is_base' => true,
            'is_default' => true,
        ]);

        $response = $this->deleteJson("/api/v1/inventory/catalog-items/{$item->id}/presentations/{$basePres->id}");
        $response->assertStatus(422)
            ->assertJsonPath('errors.presentation.0', 'No se puede eliminar la presentación base del ítem.');
    }
}
