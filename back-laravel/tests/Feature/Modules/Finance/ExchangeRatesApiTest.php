<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Services\ExchangeRateService;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExchangeRatesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
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

    public function test_user_with_exchange_rates_manage_can_upsert_daily_rate(): void
    {
        $this->authenticateWithPermissions(['exchange_rates.manage']);

        $usd = Currency::where('code', 'USD')->firstOrFail();
        $today = Carbon::today()->toDateString();

        $response = $this->putJson('/api/v1/finance/exchange-rates', [
            'currency_id' => $usd->id,
            'date' => $today,
            'buy_rate' => 3.7500,
            'sell_rate' => 3.7850,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.buy_rate', '3.7500')
            ->assertJsonPath('data.sell_rate', '3.7850');

        $this->assertDatabaseHas('exchange_rates', [
            'currency_id' => $usd->id,
            'date' => $today,
            'buy_rate' => '3.7500',
            'sell_rate' => '3.7850',
        ]);
    }

    public function test_upsert_rejects_base_currency(): void
    {
        $this->authenticateWithPermissions(['exchange_rates.manage']);

        $pen = Currency::where('code', 'PEN')->firstOrFail();

        $response = $this->putJson('/api/v1/finance/exchange-rates', [
            'currency_id' => $pen->id,
            'date' => Carbon::today()->toDateString(),
            'buy_rate' => 1.0000,
            'sell_rate' => 1.0000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.currency_id.0', 'No se puede registrar tipo de cambio para la moneda base');
    }

    public function test_upsert_rejects_sell_rate_lower_than_buy_rate(): void
    {
        $this->authenticateWithPermissions(['exchange_rates.manage']);

        $usd = Currency::where('code', 'USD')->firstOrFail();

        $response = $this->putJson('/api/v1/finance/exchange-rates', [
            'currency_id' => $usd->id,
            'date' => Carbon::today()->toDateString(),
            'buy_rate' => 3.8000,
            'sell_rate' => 3.7000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.sell_rate.0', 'El tipo de cambio de venta debe ser mayor o igual al de compra');
    }

    public function test_current_returns_stale_flag_correctly(): void
    {
        $user = $this->authenticateWithPermissions(['exchange_rates.manage']);

        $usd = Currency::where('code', 'USD')->firstOrFail();
        $yesterday = Carbon::yesterday()->toDateString();
        $today = Carbon::today()->toDateString();

        // 1. Registrar tipo de cambio de ayer
        app(ExchangeRateService::class)->upsert($usd->id, $yesterday, 3.7400, 3.7700, $user);

        // Consultar tipo del día de hoy: debe devolver el de ayer con is_stale = true
        $response = $this->getJson("/api/v1/finance/exchange-rates/current?currency_id={$usd->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_stale', true)
            ->assertJsonPath('data.rate.buy_rate', '3.7400');

        // 2. Registrar tipo de hoy
        app(ExchangeRateService::class)->upsert($usd->id, $today, 3.7500, 3.7800, $user);

        // Consultar de nuevo: debe tener is_stale = false
        $response2 = $this->getJson("/api/v1/finance/exchange-rates/current?currency_id={$usd->id}");

        $response2->assertOk()
            ->assertJsonPath('data.is_stale', false)
            ->assertJsonPath('data.rate.buy_rate', '3.7500');
    }

    public function test_conversion_between_currencies_works(): void
    {
        $user = User::factory()->create();
        $pen = Currency::where('code', 'PEN')->firstOrFail();
        $usd = Currency::where('code', 'USD')->firstOrFail();

        $service = app(ExchangeRateService::class);
        $service->upsert($usd->id, Carbon::today()->toDateString(), 3.7000, 3.8000, $user);

        // 100 USD a PEN al tipo de venta: 100 * 3.80 = 380 PEN
        $convertedPen = $service->convert(100, $usd, $pen, Carbon::today(), 'sell');
        $this->assertEquals(380.00, $convertedPen);

        // 380 PEN a USD al tipo de venta: 380 / 3.80 = 100 USD
        $convertedUsd = $service->convert(380, $pen, $usd, Carbon::today(), 'sell');
        $this->assertEquals(100.00, $convertedUsd);
    }
}
