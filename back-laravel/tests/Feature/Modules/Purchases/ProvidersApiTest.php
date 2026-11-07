<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Purchases;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\Currency;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\ProviderContact;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class ProvidersApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private const BASE = '/api/v1/purchases/providers';

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        $this->seedUbigeoFixture();
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
        return $this->authenticateWith(['providers.view', 'providers.create', 'providers.update', 'providers.delete']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'document_type' => 'RUC',
            'document_number' => '20123456789',
            'business_name' => 'Distribuidora Andina S.A.C.',
            'trade_name' => 'Andina',
            'email' => 'ventas@andina.pe',
            'credit_days' => 30,
            'is_active' => true,
        ], $overrides);
    }

    public function test_provider_can_be_created_listed_shown_updated_and_deleted(): void
    {
        $this->authenticateAsBuyer();
        $currency = Currency::factory()->create();

        $created = $this->postJson(self::BASE, $this->payload(['default_currency_id' => $currency->id]));
        $created->assertCreated()
            ->assertJsonPath('data.document_number', '20123456789')
            ->assertJsonPath('data.credit_days', 30)
            ->assertJsonPath('data.default_currency.code', $currency->code);
        $id = $created->json('data.id');

        $this->getJson(self::BASE.'?search=andina')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'/'.$id)->assertOk()->assertJsonPath('data.business_name', 'Distribuidora Andina S.A.C.');

        $this->putJson(self::BASE.'/'.$id, $this->payload(['credit_days' => 15, 'business_name' => 'Andina Norte S.A.C.']))
            ->assertOk()
            ->assertJsonPath('data.credit_days', 15);

        $this->deleteJson(self::BASE.'/'.$id)->assertNoContent();
        $this->getJson(self::BASE.'/'.$id)->assertNotFound();
    }

    public function test_list_filters_by_is_active(): void
    {
        $this->authenticateAsBuyer();
        Provider::factory()->create();
        Provider::factory()->inactive()->create();

        $this->getJson(self::BASE.'?is_active=true')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'?is_active=false')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_duplicate_document_is_rejected_and_reusable_after_delete(): void
    {
        $this->authenticateAsBuyer();
        $existing = Provider::factory()->create(['document_number' => '20123456789']);

        $this->postJson(self::BASE, $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_number');

        $this->deleteJson(self::BASE.'/'.$existing->id)->assertNoContent();

        $this->postJson(self::BASE, $this->payload())->assertCreated();
    }

    public function test_same_number_with_other_document_type_is_allowed(): void
    {
        $this->authenticateAsBuyer();
        Provider::factory()->create(['document_number' => '20123456789']);

        $this->postJson(self::BASE, $this->payload(['document_type' => 'OTHER']))->assertCreated();
    }

    public function test_invalid_ruc_is_rejected(): void
    {
        $this->authenticateAsBuyer();

        $this->postJson(self::BASE, $this->payload(['document_number' => '30123456789']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_number');

        $this->postJson(self::BASE, $this->payload(['document_number' => '2012345']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_number');
    }

    public function test_email_is_optional_but_must_be_valid_and_credit_days_are_bounded(): void
    {
        $this->authenticateAsBuyer();

        $this->postJson(self::BASE, $this->payload(['email' => null]))->assertCreated();

        $this->postJson(self::BASE, $this->payload(['document_number' => '20999999991', 'email' => 'no-es-correo']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson(self::BASE, $this->payload(['document_number' => '20999999992', 'credit_days' => 366]))
            ->assertUnprocessable()->assertJsonValidationErrors('credit_days');

        $this->postJson(self::BASE, $this->payload(['document_number' => '20999999993', 'credit_days' => -1]))
            ->assertUnprocessable()->assertJsonValidationErrors('credit_days');
    }

    public function test_ubigeo_must_exist_in_catalog(): void
    {
        $this->authenticateAsBuyer();

        $this->postJson(self::BASE, $this->payload(['ubigeo' => '999999']))
            ->assertUnprocessable()->assertJsonValidationErrors('ubigeo');
    }

    public function test_options_returns_active_providers_with_exact_document_first(): void
    {
        $this->authenticateAsBuyer();
        Provider::factory()->create(['document_number' => '20555555551', 'business_name' => 'AAA Comercial 20555555552']);
        $exact = Provider::factory()->create(['document_number' => '20555555552', 'business_name' => 'ZZZ Industrial']);
        Provider::factory()->inactive()->create(['document_number' => '20555555553', 'business_name' => 'Inactivo 20555555552']);

        $response = $this->getJson(self::BASE.'/options?search=20555555552')->assertOk();

        $ids = array_column($response->json('data'), 'id');
        $this->assertCount(2, $ids);
        $this->assertSame($exact->id, $ids[0]);
        $this->assertArrayHasKey('credit_days', $response->json('data.0'));
    }

    public function test_contacts_are_synced_created_updated_and_removed(): void
    {
        $this->authenticateAsBuyer();
        $provider = Provider::factory()->create();
        $keep = ProviderContact::create(['provider_id' => $provider->id, 'name' => 'Ana', 'is_primary' => true]);
        ProviderContact::create(['provider_id' => $provider->id, 'name' => 'Borrar']);

        $response = $this->putJson(self::BASE.'/'.$provider->id.'/contacts', ['contacts' => [
            ['id' => $keep->id, 'name' => 'Ana Torres', 'position' => 'Ventas', 'is_primary' => true],
            ['name' => 'Luis', 'email' => 'luis@proveedor.pe'],
        ]])->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertDatabaseHas('provider_contacts', ['id' => $keep->id, 'name' => 'Ana Torres']);
        $this->assertDatabaseMissing('provider_contacts', ['name' => 'Borrar']);
        $this->assertDatabaseHas('provider_contacts', ['name' => 'Luis', 'provider_id' => $provider->id]);

        $this->putJson(self::BASE.'/'.$provider->id.'/contacts', ['contacts' => []])->assertOk();
        $this->assertSame(0, ProviderContact::where('provider_id', $provider->id)->count());
    }

    public function test_two_primary_contacts_are_rejected(): void
    {
        $this->authenticateAsBuyer();
        $provider = Provider::factory()->create();

        $this->putJson(self::BASE.'/'.$provider->id.'/contacts', ['contacts' => [
            ['name' => 'A', 'is_primary' => true],
            ['name' => 'B', 'is_primary' => true],
        ]])->assertUnprocessable();

        $this->assertSame(0, ProviderContact::count());
    }

    public function test_contact_id_from_another_provider_is_rejected(): void
    {
        $this->authenticateAsBuyer();
        $provider = Provider::factory()->create();
        $other = Provider::factory()->create();
        $foreign = ProviderContact::create(['provider_id' => $other->id, 'name' => 'Ajeno']);

        $this->putJson(self::BASE.'/'.$provider->id.'/contacts', ['contacts' => [
            ['id' => $foreign->id, 'name' => 'Robado'],
        ]])->assertUnprocessable();

        $this->assertDatabaseHas('provider_contacts', ['id' => $foreign->id, 'name' => 'Ajeno', 'provider_id' => $other->id]);
    }

    public function test_bank_accounts_are_synced_and_validated(): void
    {
        $this->authenticateAsBuyer();
        $provider = Provider::factory()->create();
        $bank = Bank::factory()->create();
        $currency = Currency::factory()->create();

        $account = [
            'bank_id' => $bank->id,
            'currency_id' => $currency->id,
            'account_number' => '191-1234567-0-12',
            'cci' => '00219100123456701234',
            'is_detraction_account' => false,
        ];

        $response = $this->putJson(self::BASE.'/'.$provider->id.'/bank-accounts', ['accounts' => [$account]])->assertOk();
        $this->assertSame('191-1234567-0-12', $response->json('data.0.account_number'));
        $this->assertSame($bank->id, $response->json('data.0.bank.id'));

        $this->putJson(self::BASE.'/'.$provider->id.'/bank-accounts', ['accounts' => [array_merge($account, ['cci' => '123'])]])
            ->assertUnprocessable()->assertJsonValidationErrors('accounts.0.cci');

        $this->putJson(self::BASE.'/'.$provider->id.'/bank-accounts', ['accounts' => [$account, $account]])
            ->assertUnprocessable();

        $this->assertDatabaseCount('provider_bank_accounts', 1);

        $this->putJson(self::BASE.'/'.$provider->id.'/bank-accounts', ['accounts' => []])->assertOk();
        $this->assertDatabaseCount('provider_bank_accounts', 0);
    }

    public function test_unknown_provider_returns_404_and_bad_uuid_does_not_match(): void
    {
        $this->authenticateAsBuyer();

        $this->getJson(self::BASE.'/'.'00000000-0000-4000-8000-000000000000')->assertNotFound();
        $this->getJson(self::BASE.'/no-es-uuid')->assertNotFound();
    }

    public function test_endpoints_require_permissions(): void
    {
        $provider = Provider::factory()->create();
        $this->authenticateWith(['providers.view']);

        $this->getJson(self::BASE)->assertOk();
        $this->postJson(self::BASE, $this->payload())->assertForbidden();
        $this->putJson(self::BASE.'/'.$provider->id, $this->payload())->assertForbidden();
        $this->deleteJson(self::BASE.'/'.$provider->id)->assertForbidden();
        $this->putJson(self::BASE.'/'.$provider->id.'/contacts', ['contacts' => []])->assertForbidden();
        $this->putJson(self::BASE.'/'.$provider->id.'/bank-accounts', ['accounts' => []])->assertForbidden();
    }

    public function test_create_update_and_delete_are_independent_permissions(): void
    {
        $provider = Provider::factory()->create();

        $this->authenticateWith(['providers.view', 'providers.update']);
        $this->postJson(self::BASE, $this->payload())->assertForbidden();
        $this->deleteJson(self::BASE.'/'.$provider->id)->assertForbidden();
        $this->putJson(self::BASE.'/'.$provider->id, $this->payload(['document_number' => '20111111112']))->assertOk();

        $this->authenticateWith(['providers.view', 'providers.create']);
        $this->postJson(self::BASE, $this->payload())->assertCreated();
        $this->putJson(self::BASE.'/'.$provider->id, $this->payload(['document_number' => '20111111113']))->assertForbidden();
        $this->putJson(self::BASE.'/'.$provider->id.'/contacts', ['contacts' => []])->assertForbidden();

        $this->authenticateWith(['providers.view', 'providers.delete']);
        $this->deleteJson(self::BASE.'/'.$provider->id)->assertNoContent();
    }

    public function test_update_permission_without_view_cannot_read_prices_or_options(): void
    {
        $provider = Provider::factory()->create();
        $this->authenticateWith(['providers.update']);

        $this->getJson(self::BASE.'/options')->assertForbidden();
        $this->getJson(self::BASE.'/'.$provider->id.'/prices')->assertForbidden();
        $this->getJson('/api/v1/purchases/provider-prices/compare')->assertForbidden();
    }

    public function test_listing_requires_view_permission(): void
    {
        $this->authenticateWith([]);

        $this->getJson(self::BASE)->assertForbidden();
        $this->getJson(self::BASE.'/options')->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson(self::BASE)->assertUnauthorized();
    }

    public function test_manager_role_can_manage_but_not_delete_providers(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(Role::where('name', 'manager')->firstOrFail());
        $pair = app(JwtService::class)->createTokenPair($manager);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        $provider = Provider::factory()->create();

        $this->postJson(self::BASE, $this->payload())->assertCreated();
        $this->putJson(self::BASE.'/'.$provider->id, $this->payload(['document_number' => '20111111111']))->assertOk();
        $this->deleteJson(self::BASE.'/'.$provider->id)->assertForbidden();
    }
}
