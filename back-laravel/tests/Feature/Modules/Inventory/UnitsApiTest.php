<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitsApiTest extends TestCase
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

    public function test_unit_categories_crud(): void
    {
        $this->authenticateAsAdmin();

        // 1. Create
        $response = $this->postJson('/api/v1/inventory/unit-categories', [
            'name' => 'Longitud',
            'description' => 'Medidas de longitud',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Longitud');

        $categoryId = (string) $response->json('data.id');

        // 2. Retrieve
        $this->getJson("/api/v1/inventory/unit-categories/{$categoryId}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Longitud');

        // 3. Update
        $this->putJson("/api/v1/inventory/unit-categories/{$categoryId}", [
            'name' => 'Longitud y Distancia',
        ])->assertStatus(200)
            ->assertJsonPath('data.name', 'Longitud y Distancia');

        // 4. Delete
        $this->deleteJson("/api/v1/inventory/unit-categories/{$categoryId}")
            ->assertStatus(204);

        $this->assertSoftDeleted('unit_categories', ['id' => $categoryId]);
    }

    public function test_second_base_unit_in_same_category_returns_422(): void
    {
        $this->authenticateAsAdmin();

        $category = UnitCategory::factory()->create(['name' => 'Tiempo']);

        // First unit as base -> Allowed
        $this->postJson('/api/v1/inventory/units', [
            'unit_category_id' => $category->id,
            'name' => 'Segundo',
            'symbol' => 's',
            'is_base' => true,
            'allow_decimals' => true,
        ])->assertStatus(201);

        // Second unit marked as base in same category -> 422
        $response = $this->postJson('/api/v1/inventory/units', [
            'unit_category_id' => $category->id,
            'name' => 'Minuto',
            'symbol' => 'min',
            'is_base' => true,
            'allow_decimals' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.is_base.0', 'La categoría de unidad ya cuenta con una unidad base.');
    }

    public function test_derived_unit_cannot_be_created_without_existing_base_unit(): void
    {
        $this->authenticateAsAdmin();

        $category = UnitCategory::factory()->create(['name' => 'Área']);

        // Try to create derived unit when category has no base unit -> 422
        $response = $this->postJson('/api/v1/inventory/units', [
            'unit_category_id' => $category->id,
            'name' => 'Centímetro cuadrado',
            'symbol' => 'cm2',
            'is_base' => false,
            'conversion_factor' => 0.0001,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.is_base.0', 'La categoría debe tener primero una unidad base antes de crear unidades derivadas.');
    }

    public function test_units_conversion_calculation(): void
    {
        $this->authenticateAsAdmin();

        $category = UnitCategory::factory()->create(['name' => 'Cantidad']);

        $baseUnit = Unit::factory()->base()->create([
            'unit_category_id' => $category->id,
            'name' => 'Unidad',
            'symbol' => 'NIU',
            'conversion_factor' => '1.000000',
        ]);

        $dozenUnit = Unit::factory()->create([
            'unit_category_id' => $category->id,
            'name' => 'Docena',
            'symbol' => 'DOC',
            'is_base' => false,
            'conversion_factor' => '12.000000',
        ]);

        // Convert 12 unidades to docena -> should be 1
        $res1 = $this->postJson('/api/v1/inventory/units/convert', [
            'quantity' => 12,
            'from_unit_id' => $baseUnit->id,
            'to_unit_id' => $dozenUnit->id,
        ]);

        $res1->assertStatus(200)
            ->assertJsonPath('data.quantity', 1);

        // Convert 2 docenas to unidades -> should be 24
        $res2 = $this->postJson('/api/v1/inventory/units/convert', [
            'quantity' => 2,
            'from_unit_id' => $dozenUnit->id,
            'to_unit_id' => $baseUnit->id,
        ]);

        $res2->assertStatus(200)
            ->assertJsonPath('data.quantity', 24);
    }

    public function test_conversion_between_different_categories_fails(): void
    {
        $this->authenticateAsAdmin();

        $catQuantity = UnitCategory::factory()->create(['name' => 'Cantidad']);
        $unitQuantity = Unit::factory()->base()->create([
            'unit_category_id' => $catQuantity->id,
            'name' => 'Unidad',
            'symbol' => 'NIU',
        ]);

        $catVolume = UnitCategory::factory()->create(['name' => 'Volumen']);
        $unitVolume = Unit::factory()->base()->create([
            'unit_category_id' => $catVolume->id,
            'name' => 'Litro',
            'symbol' => 'L',
        ]);

        $response = $this->postJson('/api/v1/inventory/units/convert', [
            'quantity' => 5,
            'from_unit_id' => $unitQuantity->id,
            'to_unit_id' => $unitVolume->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.units.0', 'No se puede convertir entre unidades de distintas categorías.');
    }

    public function test_cannot_delete_category_with_units(): void
    {
        $this->authenticateAsAdmin();

        $category = UnitCategory::factory()->create(['name' => 'Fuerza']);
        Unit::factory()->base()->create([
            'unit_category_id' => $category->id,
            'name' => 'Newton',
            'symbol' => 'N',
        ]);

        $this->deleteJson("/api/v1/inventory/unit-categories/{$category->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.unit_category.0', 'No se puede eliminar una categoría que tiene unidades registradas.');
    }

    public function test_packaging_unit_is_created_without_base_and_with_neutral_factor(): void
    {
        $this->authenticateAsAdmin();

        $category = UnitCategory::factory()->create(['name' => 'Empaque', 'is_packaging' => true]);

        $response = $this->postJson('/api/v1/inventory/units', [
            'unit_category_id' => $category->id,
            'name' => 'Caja',
            'symbol' => 'CJA',
            'is_base' => true,
            'conversion_factor' => 24,
            'sunat_code' => 'BX',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_base', false)
            ->assertJsonPath('data.conversion_factor', '1.000000');

        $this->postJson('/api/v1/inventory/units', [
            'unit_category_id' => $category->id,
            'name' => 'Paquete',
            'symbol' => 'PQT',
        ])->assertStatus(201);
    }

    public function test_conversion_with_packaging_units_fails(): void
    {
        $this->authenticateAsAdmin();

        $category = UnitCategory::factory()->create(['name' => 'Empaque', 'is_packaging' => true]);
        $box = Unit::factory()->create(['unit_category_id' => $category->id, 'name' => 'Caja', 'symbol' => 'CJA']);
        $bag = Unit::factory()->create(['unit_category_id' => $category->id, 'name' => 'Bolsa', 'symbol' => 'BLS']);

        $this->postJson('/api/v1/inventory/units/convert', [
            'quantity' => 1,
            'from_unit_id' => $box->id,
            'to_unit_id' => $bag->id,
        ])->assertStatus(422)
            ->assertJsonPath('errors.units.0', 'Las unidades de empaque no tienen un factor fijo: la conversión se define en la presentación de cada producto.');
    }

    public function test_packaging_flag_cannot_change_when_category_has_units(): void
    {
        $this->authenticateAsAdmin();

        $category = UnitCategory::factory()->create(['name' => 'Cantidad']);
        Unit::factory()->base()->create(['unit_category_id' => $category->id, 'name' => 'Unidad', 'symbol' => 'UND']);

        $this->putJson("/api/v1/inventory/unit-categories/{$category->id}", [
            'name' => 'Cantidad',
            'is_packaging' => true,
        ])->assertStatus(422)
            ->assertJsonPath('errors.is_packaging.0', 'No se puede cambiar el tipo de empaque de una categoría que tiene unidades registradas.');
    }

    public function test_unit_category_options_expose_packaging_flag(): void
    {
        $this->authenticateAsAdmin();

        UnitCategory::factory()->create(['name' => 'Empaque', 'is_packaging' => true]);

        $this->getJson('/api/v1/inventory/unit-categories/options')
            ->assertStatus(200)
            ->assertJsonPath('data.0.is_packaging', true);
    }
}
