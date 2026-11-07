<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Configuration\Services\CompanySettingService;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\ExchangeRate;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrenciesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
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

    public function test_options_are_accessible_to_any_authenticated_user(): void
    {
        $user = User::factory()->create();
        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        $response = $this->getJson('/api/v1/finance/currencies/options');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'symbol', 'is_base']]]);
    }

    public function test_currencies_can_be_listed_with_permission(): void
    {
        $this->authenticateWithPermissions(['finance_settings.view']);

        $response = $this->getJson('/api/v1/finance/currencies');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta' => ['total', 'current_page']]);
    }

    public function test_currencies_can_be_created_retrieved_updated_and_deleted(): void
    {
        $this->authenticateAsAdmin();

        // 1. Create
        $response = $this->postJson('/api/v1/finance/currencies', [
            'code' => 'GBP',
            'name' => 'Libra Esterlina',
            'symbol' => '£',
            'sunat_code' => 'GBP',
            'decimals' => 2,
            'is_base' => false,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'GBP')
            ->assertJsonPath('data.is_base', false);

        $currencyId = (string) $response->json('data.id');

        // 2. Show
        $this->getJson("/api/v1/finance/currencies/{$currencyId}")
            ->assertOk()
            ->assertJsonPath('data.code', 'GBP');

        // 3. Update
        $this->putJson("/api/v1/finance/currencies/{$currencyId}", [
            'code' => 'GBP',
            'name' => 'Libra Esterlina Actualizada',
            'symbol' => '£',
            'decimals' => 2,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Libra Esterlina Actualizada');

        // 4. Delete
        $this->deleteJson("/api/v1/finance/currencies/{$currencyId}")
            ->assertNoContent();

        $this->assertSoftDeleted('currencies', ['id' => $currencyId]);
    }

    public function test_changing_base_currency_updates_company_settings(): void
    {
        $this->authenticateAsAdmin();

        $usd = Currency::where('code', 'USD')->firstOrFail();
        $this->assertFalse($usd->is_base);

        $response = $this->putJson("/api/v1/finance/currencies/{$usd->id}", [
            'code' => 'USD',
            'name' => $usd->name,
            'symbol' => $usd->symbol,
            'decimals' => $usd->decimals,
            'is_base' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_base', true);

        // La moneda base anterior (PEN) ya no debe ser base
        $pen = Currency::where('code', 'PEN')->firstOrFail();
        $this->assertFalse($pen->is_base);

        // company_settings.default_currency_code debe haberse sincronizado a USD
        $company = app(CompanySettingService::class)->current();
        $this->assertSame('USD', $company->default_currency_code);
    }

    public function test_changing_base_currency_fails_when_operations_exist(): void
    {
        $this->authenticateAsAdmin();

        $usd = Currency::where('code', 'USD')->firstOrFail();

        // Registrar un tipo de cambio para simular operaciones
        ExchangeRate::create([
            'currency_id' => $usd->id,
            'date' => '2026-10-18',
            'buy_rate' => 3.7500,
            'sell_rate' => 3.7800,
        ]);

        $response = $this->putJson("/api/v1/finance/currencies/{$usd->id}", [
            'code' => 'USD',
            'name' => $usd->name,
            'symbol' => $usd->symbol,
            'decimals' => $usd->decimals,
            'is_base' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.is_base.0', 'No se puede cambiar la moneda base con operaciones registradas');
    }

    public function test_cannot_deactivate_or_delete_base_currency(): void
    {
        $this->authenticateAsAdmin();

        $base = Currency::where('is_base', true)->firstOrFail();

        // Intentar desactivar
        $this->putJson("/api/v1/finance/currencies/{$base->id}", [
            'code' => $base->code,
            'name' => $base->name,
            'symbol' => $base->symbol,
            'decimals' => $base->decimals,
            'is_active' => false,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.is_active.0', 'La moneda base no se puede desactivar');

        // Intentar eliminar
        $this->deleteJson("/api/v1/finance/currencies/{$base->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_base.0', 'La moneda base no se puede eliminar');
    }
}
