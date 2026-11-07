<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Purchases;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Services\ExchangeRateService;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\ProviderPrice;
use App\Modules\Purchases\Services\ProviderPriceService;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProviderPricesApiTest extends TestCase
{
    use RefreshDatabase;

    private Currency $pen;

    private Currency $usd;

    private ItemPresentation $presentation;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();

        Currency::clearBaseCache();
        $this->pen = Currency::factory()->base()->create(['code' => 'PEN', 'decimals' => 2]);
        $this->usd = Currency::factory()->create(['code' => 'USD', 'decimals' => 2]);
        app(ExchangeRateService::class)->upsert($this->usd->id, Carbon::today()->toDateString(), 3.70, 3.80);

        $this->presentation = ItemPresentation::factory()->create();
    }

    protected function tearDown(): void
    {
        Currency::clearBaseCache();
        parent::tearDown();
    }

    private function authenticateWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $slug) {
            $user->givePermissionTo(Permission::where('slug', $slug)->firstOrFail());
        }

        $pair = app(JwtService::class)->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    private function authenticateAsBuyer(): User
    {
        return $this->authenticateWith(['providers.view', 'providers.update']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'item_presentation_id' => $this->presentation->id,
            'currency_id' => $this->pen->id,
            'price' => '12.5000',
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-06-30',
        ], $overrides);
    }

    private function url(Provider $provider): string
    {
        return '/api/v1/purchases/providers/'.$provider->id.'/prices';
    }

    public function test_price_can_be_created_listed_updated_and_deleted(): void
    {
        $this->authenticateAsBuyer();
        $provider = Provider::factory()->create();

        $created = $this->postJson($this->url($provider), $this->payload())->assertCreated();
        $created->assertJsonPath('data.price', '12.5000')->assertJsonPath('data.currency.code', 'PEN');
        $id = $created->json('data.id');

        $this->getJson($this->url($provider))->assertOk()->assertJsonCount(1, 'data');

        $this->putJson('/api/v1/purchases/provider-prices/'.$id, $this->payload(['price' => '13.0000']))
            ->assertOk()->assertJsonPath('data.price', '13.0000');

        $this->deleteJson('/api/v1/purchases/provider-prices/'.$id)->assertNoContent();
        $this->assertDatabaseCount('provider_prices', 0);
    }

    public function test_overlapping_validity_is_rejected(): void
    {
        $this->authenticateAsBuyer();
        $provider = Provider::factory()->create();
        $this->postJson($this->url($provider), $this->payload())->assertCreated();

        $this->postJson($this->url($provider), $this->payload(['valid_from' => '2026-06-30', 'valid_until' => '2026-12-31']))
            ->assertUnprocessable();

        $this->postJson($this->url($provider), $this->payload(['valid_from' => '2025-12-01', 'valid_until' => '2026-01-01']))
            ->assertUnprocessable();

        $this->postJson($this->url($provider), $this->payload(['valid_from' => '2026-03-01', 'valid_until' => '2026-04-01']))
            ->assertUnprocessable();

        $this->postJson($this->url($provider), $this->payload(['valid_from' => '2025-01-01', 'valid_until' => null]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('provider_prices', 1);
    }

    public function test_adjacent_validity_and_other_scopes_are_allowed(): void
    {
        $this->authenticateAsBuyer();
        $provider = Provider::factory()->create();
        $this->postJson($this->url($provider), $this->payload())->assertCreated();

        $this->postJson($this->url($provider), $this->payload(['valid_from' => '2026-07-01', 'valid_until' => null]))->assertCreated();

        $this->postJson($this->url($provider), $this->payload(['currency_id' => $this->usd->id]))->assertCreated();

        $otherPresentation = ItemPresentation::factory()->create();
        $this->postJson($this->url($provider), $this->payload(['item_presentation_id' => $otherPresentation->id]))->assertCreated();

        $otherProvider = Provider::factory()->create();
        $this->postJson($this->url($otherProvider), $this->payload())->assertCreated();
    }

    public function test_open_ended_validity_blocks_later_ranges(): void
    {
        $this->authenticateAsBuyer();
        $provider = Provider::factory()->create();
        $this->postJson($this->url($provider), $this->payload(['valid_until' => null]))->assertCreated();

        $this->postJson($this->url($provider), $this->payload(['valid_from' => '2030-01-01', 'valid_until' => '2030-12-31']))
            ->assertUnprocessable();
    }

    public function test_update_ignores_itself_when_checking_overlap(): void
    {
        $this->authenticateAsBuyer();
        $provider = Provider::factory()->create();
        $id = $this->postJson($this->url($provider), $this->payload())->json('data.id');

        $this->putJson('/api/v1/purchases/provider-prices/'.$id, $this->payload(['valid_until' => '2026-09-30']))->assertOk();
    }

    public function test_price_validation_rules(): void
    {
        $this->authenticateAsBuyer();
        $provider = Provider::factory()->create();

        $this->postJson($this->url($provider), $this->payload(['price' => -1]))->assertUnprocessable()->assertJsonValidationErrors('price');
        $this->postJson($this->url($provider), $this->payload(['price' => '1.23456']))->assertUnprocessable()->assertJsonValidationErrors('price');
        $this->postJson($this->url($provider), $this->payload(['valid_until' => '2025-12-31']))->assertUnprocessable()->assertJsonValidationErrors('valid_until');
        $this->postJson($this->url($provider), $this->payload(['item_presentation_id' => '00000000-0000-4000-8000-000000000000']))
            ->assertUnprocessable()->assertJsonValidationErrors('item_presentation_id');
    }

    public function test_compare_orders_current_prices_by_converted_amount(): void
    {
        $this->authenticateAsBuyer();
        $today = Carbon::today();

        $cheapUsd = Provider::factory()->create(['business_name' => 'Barato USD']);   // 3.00 USD * 3.70 = 11.10 PEN
        $midPen = Provider::factory()->create(['business_name' => 'Medio PEN']);      // 12.00 PEN
        $expensive = Provider::factory()->create(['business_name' => 'Caro USD']);    // 4.00 USD * 3.70 = 14.80 PEN
        $expired = Provider::factory()->create(['business_name' => 'Vencido']);
        $inactive = Provider::factory()->inactive()->create(['business_name' => 'Inactivo']);

        $make = fn (Provider $p, Currency $c, string $price, ?Carbon $from = null, ?Carbon $until = null) => ProviderPrice::factory()->create([
            'provider_id' => $p->id,
            'item_presentation_id' => $this->presentation->id,
            'currency_id' => $c->id,
            'price' => $price,
            'valid_from' => ($from ?? $today->copy()->subMonth())->toDateString(),
            'valid_until' => $until?->toDateString(),
        ]);

        $make($expensive, $this->usd, '4.0000');
        $make($midPen, $this->pen, '12.0000');
        $make($cheapUsd, $this->usd, '3.0000');
        $make($expired, $this->pen, '1.0000', $today->copy()->subYear(), $today->copy()->subMonths(2));
        $make($inactive, $this->pen, '2.0000');

        $this->presentation->item->update(['last_purchase_cost' => '10.500000']);

        $response = $this->getJson('/api/v1/purchases/provider-prices/compare?item_presentation_id='.$this->presentation->id)->assertOk();

        $names = array_column(array_column($response->json('data.prices'), 'provider'), 'business_name');
        $this->assertSame(['Barato USD', 'Medio PEN', 'Caro USD'], $names);
        $this->assertEquals(11.10, $response->json('data.prices.0.base_price'));
        $this->assertSame('10.500000', $response->json('data.last_purchase_cost'));
    }

    public function test_compare_puts_prices_without_exchange_rate_last(): void
    {
        $this->authenticateAsBuyer();
        $eur = Currency::factory()->create(['code' => 'EUR']);

        $noRate = Provider::factory()->create(['business_name' => 'Sin tipo de cambio']);
        $pen = Provider::factory()->create(['business_name' => 'En soles']);

        foreach ([[$noRate, $eur, '1.0000'], [$pen, $this->pen, '50.0000']] as [$p, $c, $price]) {
            ProviderPrice::factory()->create([
                'provider_id' => $p->id,
                'item_presentation_id' => $this->presentation->id,
                'currency_id' => $c->id,
                'price' => $price,
            ]);
        }

        $response = $this->getJson('/api/v1/purchases/provider-prices/compare?item_presentation_id='.$this->presentation->id)->assertOk();

        $this->assertSame(['En soles', 'Sin tipo de cambio'], array_column(array_column($response->json('data.prices'), 'provider'), 'business_name'));
        $this->assertNull($response->json('data.prices.1.base_price'));
    }

    public function test_compare_requires_a_valid_presentation(): void
    {
        $this->authenticateAsBuyer();

        $this->getJson('/api/v1/purchases/provider-prices/compare')->assertUnprocessable();
    }

    public function test_record_purchase_updates_current_price_or_creates_one(): void
    {
        $provider = Provider::factory()->create();
        $service = app(ProviderPriceService::class);
        $at = Carbon::parse('2026-10-19 10:00:00');

        $created = $service->recordPurchase($provider, $this->presentation, $this->pen, '9.8000', $at);
        $this->assertSame('9.8000', $created->last_purchase_price);
        $this->assertSame('2026-10-19', $created->valid_from->toDateString());
        $this->assertNull($created->valid_until);

        $updated = $service->recordPurchase($provider, $this->presentation, $this->pen, '10.2000', $at->copy()->addDay());
        $this->assertSame($created->id, $updated->id);
        $this->assertSame('10.2000', $updated->fresh()->last_purchase_price);
        $this->assertSame('9.8000', $updated->fresh()->price);
        $this->assertDatabaseCount('provider_prices', 1);

        $service->recordPurchase($provider, $this->presentation, $this->usd, '2.0000', $at);
        $this->assertDatabaseCount('provider_prices', 2);
    }

    public function test_price_endpoints_require_permissions(): void
    {
        $provider = Provider::factory()->create();
        $price = ProviderPrice::factory()->create(['provider_id' => $provider->id]);
        $this->authenticateWith(['providers.view']);

        $this->getJson($this->url($provider))->assertOk();
        $this->getJson('/api/v1/purchases/provider-prices/compare?item_presentation_id='.$price->item_presentation_id)->assertOk();
        $this->postJson($this->url($provider), $this->payload())->assertForbidden();
        $this->putJson('/api/v1/purchases/provider-prices/'.$price->id, $this->payload())->assertForbidden();
        $this->deleteJson('/api/v1/purchases/provider-prices/'.$price->id)->assertForbidden();
    }

    public function test_unknown_price_returns_404(): void
    {
        $this->authenticateAsBuyer();

        $this->putJson('/api/v1/purchases/provider-prices/00000000-0000-4000-8000-000000000000', $this->payload())->assertNotFound();
        $this->deleteJson('/api/v1/purchases/provider-prices/00000000-0000-4000-8000-000000000000')->assertNotFound();
    }
}
