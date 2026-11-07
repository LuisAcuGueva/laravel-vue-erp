<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Clients\Exceptions\ClientRuleException;
use App\Modules\Clients\Models\Client;
use App\Modules\Clients\Models\ClientContact;
use App\Modules\Clients\Services\ClientService;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Services\ExchangeRateService;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\ItemPrice;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\CatalogItemService;
use App\Modules\Maintenance\Exceptions\EmployeeRuleException;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Maintenance\Services\EmployeeService;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Sales\Enums\QuotationStatus;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\QuotationItem;
use App\Modules\Users\Models\User;
use Database\Seeders\ClientSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QuotationsApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/sales/quotations';

    protected Branch $branch;

    protected Branch $otherBranch;

    protected Warehouse $warehouse;

    protected Currency $pen;

    protected Currency $usd;

    protected Client $client;

    protected ClientContact $contact;

    protected Employee $seller;

    protected CatalogItem $product;

    protected ItemPresentation $presentation;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(SystemParameterService::CACHE_KEY);
        $this->seed(SystemParameterSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ClientSeeder::class);

        Currency::clearBaseCache();
        $this->pen = Currency::factory()->base()->create(['code' => 'PEN', 'symbol' => 'S/', 'decimals' => 2]);
        $this->usd = Currency::factory()->create(['code' => 'USD', 'symbol' => '$', 'decimals' => 2]);
        app(ExchangeRateService::class)->upsert($this->usd->id, Carbon::today()->toDateString(), 3.70, 3.80);

        $this->branch = Branch::factory()->create(['code' => 'SEDE-A', 'is_active' => true]);
        $this->otherBranch = Branch::factory()->create(['code' => 'SEDE-B', 'is_active' => true]);
        $this->warehouse = Warehouse::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);

        $this->client = Client::factory()->create([
            'name' => 'Constructora Los Andes S.A.C.',
            'default_price_type' => PriceType::Retail,
            'is_active' => true,
        ]);

        $this->contact = ClientContact::create([
            'client_id' => $this->client->id,
            'name' => 'Ing. Juan Pérez',
            'email' => 'juan.perez@andes.pe',
            'phone' => '999888777',
            'position' => 'Gerente de Proyectos',
            'is_primary' => true,
        ]);

        $this->seller = Employee::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->product = CatalogItem::factory()->create([
            'name' => 'Filtro de Aceite Premium',
            'is_sellable' => true,
            'is_active' => true,
            'tax_type' => TaxType::Taxable,
        ]);

        $this->presentation = ItemPresentation::factory()->create([
            'catalog_item_id' => $this->product->id,
            'unit_id' => $this->product->base_unit_id,
            'name' => 'Unidad',
            'factor' => 1.0,
            'is_base' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Precios: Minorista 100.00, Mayorista 80.00 (mínimo 10)
        ItemPrice::create([
            'item_presentation_id' => $this->presentation->id,
            'price_type' => PriceType::Retail,
            'price' => 100.0000,
            'min_quantity' => 1,
        ]);

        ItemPrice::create([
            'item_presentation_id' => $this->presentation->id,
            'price_type' => PriceType::Wholesale,
            'price' => 80.0000,
            'min_quantity' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        Currency::clearBaseCache();
        parent::tearDown();
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function authenticateWith(array $permissions = [], bool $bothBranches = false): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $slug) {
            $user->givePermissionTo(Permission::where('slug', $slug)->firstOrFail());
        }

        $user->branches()->sync($bothBranches
            ? [$this->branch->id => ['is_default' => true], $this->otherBranch->id => ['is_default' => false]]
            : [$this->branch->id => ['is_default' => true]]);

        $token = app(JwtService::class)->createTokenPair($user)['access_token'];

        $this->withHeader('Authorization', 'Bearer '.$token);
        $this->withHeader('X-Branch-Id', $this->branch->id);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPayload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'contact_id' => $this->contact->id,
            'seller_id' => $this->seller->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'currency_id' => $this->pen->id,
            'price_type' => 'retail',
            'prices_include_tax' => true,
            'global_discount_percent' => 0,
            'notes' => 'Validez de oferta 15 días',
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 2,
                    'discount_percent' => 5,
                ],
            ],
        ], $overrides);
    }

    public function test_quotation_can_be_created_with_calculated_totals(): void
    {
        $this->authenticateWith(['quotations.create', 'quotations.view']);

        $response = $this->postJson(self::BASE, $this->defaultPayload());

        $response->assertCreated();
        $this->assertDatabaseHas('quotations', [
            'client_id' => $this->client->id,
            'status' => QuotationStatus::Draft->value,
        ]);

        $data = $response->json('data');
        $this->assertStringStartsWith('COT-', $data['number']);
        $this->assertEquals(QuotationStatus::Draft->value, $data['status']);
        $this->assertCount(1, $data['items']);
        $this->assertEquals(100.0, $data['items'][0]['list_price']);
        $this->assertEquals(100.0, $data['items'][0]['unit_price']);
        $this->assertEquals(5.0, $data['items'][0]['discount_percent']);
        $this->assertGreaterThan(0, $data['total']);
    }

    public function test_quotation_creation_uses_wholesale_price_when_quantity_meets_minimum(): void
    {
        $this->authenticateWith(['quotations.create', 'quotations.view']);

        $payload = $this->defaultPayload([
            'price_type' => 'wholesale',
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 10, // Cumple el mínimo de 10 para mayorista
                    'discount_percent' => 0,
                ],
            ],
        ]);

        $response = $this->postJson(self::BASE, $payload);
        $response->assertCreated();

        $item = $response->json('data.items.0');
        $this->assertEquals(80.0, $item['list_price']);
        $this->assertEquals(80.0, $item['unit_price']);
    }

    public function test_quotation_creation_falls_back_to_retail_when_wholesale_minimum_not_met(): void
    {
        $this->authenticateWith(['quotations.create', 'quotations.view']);

        $payload = $this->defaultPayload([
            'price_type' => 'wholesale',
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 5, // No cumple el mínimo de 10
                    'discount_percent' => 0,
                ],
            ],
        ]);

        $response = $this->postJson(self::BASE, $payload);
        $response->assertCreated();

        $item = $response->json('data.items.0');
        $this->assertEquals(100.0, $item['list_price']);
    }

    public function test_quotation_creation_converts_prices_when_currency_is_not_base(): void
    {
        $this->authenticateWith(['quotations.create', 'quotations.view']);

        $payload = $this->defaultPayload([
            'currency_id' => $this->usd->id,
            'exchange_rate' => 3.80,
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 1,
                    'discount_percent' => 0,
                ],
            ],
        ]);

        $response = $this->postJson(self::BASE, $payload);
        $response->assertCreated();

        $item = $response->json('data.items.0');
        // 100 / 3.80 = 26.3158
        $this->assertEquals(26.3158, $item['list_price']);
    }

    public function test_quotation_creation_fails_when_line_discount_exceeds_max_without_permission(): void
    {
        $this->authenticateWith(['quotations.create']);

        // Tope es 10%. Enviamos 15%
        $payload = $this->defaultPayload([
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 1,
                    'discount_percent' => 15,
                ],
            ],
        ]);

        $response = $this->postJson(self::BASE, $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['items.0.discount_percent']]);
    }

    public function test_quotation_creation_succeeds_when_line_discount_exceeds_max_with_override_permission(): void
    {
        $user = $this->authenticateWith(['quotations.create', 'sales.discount_override']);

        $payload = $this->defaultPayload([
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 1,
                    'discount_percent' => 20,
                ],
            ],
        ]);

        $response = $this->postJson(self::BASE, $payload);

        $response->assertCreated();
        $this->assertEquals($user->id, $response->json('data.discount_override_by'));
    }

    public function test_quotation_creation_fails_when_global_discount_exceeds_max_without_permission(): void
    {
        $this->authenticateWith(['quotations.create']);

        $payload = $this->defaultPayload([
            'global_discount_percent' => 15, // Tope es 10%
        ]);

        $response = $this->postJson(self::BASE, $payload);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['global_discount_percent']]);
    }

    public function test_quotation_creation_fails_for_system_client(): void
    {
        $this->authenticateWith(['quotations.create']);

        $systemClient = Client::where('is_system', true)->firstOrFail();

        $payload = $this->defaultPayload([
            'client_id' => $systemClient->id,
            'contact_id' => null,
        ]);

        $response = $this->postJson(self::BASE, $payload);

        $response->assertStatus(422)
            ->assertJsonPath('errors.client_id.0', 'No se pueden emitir cotizaciones al cliente de sistema.');
    }

    public function test_quotation_creation_defaults_seller_to_user_employee_when_available(): void
    {
        $user = $this->authenticateWith(['quotations.create']);
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $payload = $this->defaultPayload([
            'seller_id' => null,
        ]);

        $response = $this->postJson(self::BASE, $payload);
        $response->assertCreated();
        $this->assertEquals($employee->id, $response->json('data.seller_id'));
    }

    public function test_quotation_can_be_retrieved_by_id(): void
    {
        $this->authenticateWith(['quotations.view']);

        $quotation = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'seller_id' => $this->seller->id,
        ]);

        $response = $this->getJson(self::BASE."/{$quotation->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $quotation->id)
            ->assertJsonPath('data.seller.id', $this->seller->id)
            ->assertJsonPath('data.seller.name', $this->seller->full_name)
            ->assertJsonPath('data.seller.full_name', $this->seller->full_name);
    }

    public function test_quotation_can_be_updated_in_draft_status(): void
    {
        $this->authenticateWith(['quotations.update', 'quotations.view']);

        $quotation = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'status' => QuotationStatus::Draft,
        ]);

        $payload = $this->defaultPayload([
            'notes' => 'Notas actualizadas',
        ]);

        $response = $this->putJson(self::BASE."/{$quotation->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.notes', 'Notas actualizadas');
    }

    public function test_quotation_cannot_be_updated_when_not_in_draft(): void
    {
        $this->authenticateWith(['quotations.update']);

        $quotation = Quotation::factory()->sent()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
        ]);

        $payload = $this->defaultPayload();

        $response = $this->putJson(self::BASE."/{$quotation->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('errors.sales.0', 'Solo se pueden editar cotizaciones en estado borrador.');
    }

    public function test_quotation_status_workflow_transitions(): void
    {
        $this->authenticateWith(['quotations.update', 'quotations.cancel']);

        $quotation = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'status' => QuotationStatus::Draft,
            'valid_until' => now()->addDays(5)->toDateString(),
        ]);

        // 1. Draft -> Sent
        $this->postJson(self::BASE."/{$quotation->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', QuotationStatus::Sent->value);

        // 2. Sent -> Accepted
        $this->postJson(self::BASE."/{$quotation->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', QuotationStatus::Accepted->value);

        // 3. Accepted -> Cancelled
        $this->postJson(self::BASE."/{$quotation->id}/cancel", ['reason' => 'Cliente canceló el proyecto'])
            ->assertOk()
            ->assertJsonPath('data.status', QuotationStatus::Cancelled->value);
    }

    public function test_quotation_cannot_be_accepted_when_expired(): void
    {
        $this->authenticateWith(['quotations.update']);

        $quotation = Quotation::factory()->sent()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'valid_until' => now()->subDay()->toDateString(), // Vencida ayer
        ]);

        $response = $this->postJson(self::BASE."/{$quotation->id}/accept");

        $response->assertStatus(422)
            ->assertJsonPath('errors.sales.0', 'No se puede aceptar una cotización vencida.');
    }

    public function test_quotation_can_be_duplicated_and_omits_inactive_items(): void
    {
        $this->authenticateWith(['quotations.create', 'quotations.view']);

        $quotation = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'status' => QuotationStatus::Sent,
        ]);

        // Línea activa
        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'line' => 1,
            'catalog_item_id' => $this->product->id,
            'item_presentation_id' => $this->presentation->id,
            'description' => 'Línea activa',
            'quantity' => 2,
            'list_price' => 100,
            'unit_price' => 100,
            'discount_percent' => 0,
            'effective_discount_percent' => 0,
            'tax_type' => TaxType::Taxable,
            'line_subtotal' => 200,
            'line_tax' => 36,
            'line_total' => 236,
        ]);

        // Línea inactiva
        $inactiveProduct = CatalogItem::factory()->create(['is_active' => false]);
        $inactivePresentation = ItemPresentation::factory()->create([
            'catalog_item_id' => $inactiveProduct->id,
            'is_active' => false,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'line' => 2,
            'catalog_item_id' => $inactiveProduct->id,
            'item_presentation_id' => $inactivePresentation->id,
            'description' => 'Línea que debe omitirse',
            'quantity' => 1,
            'list_price' => 50,
            'unit_price' => 50,
            'discount_percent' => 0,
            'effective_discount_percent' => 0,
            'tax_type' => TaxType::Taxable,
            'line_subtotal' => 50,
            'line_tax' => 9,
            'line_total' => 59,
        ]);

        $response = $this->postJson(self::BASE."/{$quotation->id}/duplicate");

        $response->assertCreated();
        $this->assertEquals([2], $response->json('meta.skipped_lines'));
        $this->assertCount(1, $response->json('data.items'));
        $this->assertNotEquals($quotation->number, $response->json('data.number'));
    }

    public function test_quotation_availability_returns_stock_in_active_branch_warehouses(): void
    {
        $this->authenticateWith(['quotations.view']);

        Stock::forceCreate([
            'warehouse_id' => $this->warehouse->id,
            'catalog_item_id' => $this->product->id,
            'quantity' => 25,
            'min_stock' => 5,
        ]);

        $quotation = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'line' => 1,
            'catalog_item_id' => $this->product->id,
            'item_presentation_id' => $this->presentation->id,
            'description' => 'Filtro',
            'quantity' => 5,
            'unit_price' => 100,
            'tax_type' => TaxType::Taxable,
            'line_subtotal' => 500,
            'line_tax' => 90,
            'line_total' => 590,
        ]);

        $response = $this->getJson(self::BASE."/{$quotation->id}/availability");

        $response->assertOk();
        $this->assertEquals([
            [
                'line' => 1,
                'available' => 25.0,
            ],
        ], $response->json('data'));
    }

    public function test_quotation_pdf_returns_valid_response(): void
    {
        $this->authenticateWith(['quotations.view']);

        $quotation = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'currency_id' => $this->pen->id,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'line' => 1,
            'description' => 'Servicio de mantenimiento',
            'quantity' => 1,
            'unit_price' => 150,
            'tax_type' => TaxType::Taxable,
            'line_subtotal' => 150,
            'line_tax' => 27,
            'line_total' => 177,
        ]);

        $response = $this->get(self::BASE."/{$quotation->id}/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    public function test_quotations_visibility_filter_without_view_all_permission(): void
    {
        // Usuario 1 sin quotations.view_all
        $user1 = $this->authenticateWith(['quotations.view']);

        $employee1 = Employee::factory()->create([
            'user_id' => $user1->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // Cotización creada por user1
        $q1 = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'created_by' => $user1->id,
            'seller_id' => $employee1->id,
        ]);

        // Cotización creada por otro usuario
        $otherUser = User::factory()->create();
        $q2 = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'created_by' => $otherUser->id,
            'seller_id' => null,
        ]);

        $response = $this->getJson(self::BASE);
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($q1->id, $ids);
        $this->assertNotContains($q2->id, $ids);

        // Si le otorgamos quotations.view_all, ahora ve ambas
        $user1->givePermissionTo(Permission::where('slug', 'quotations.view_all')->firstOrFail());

        $responseAll = $this->getJson(self::BASE);
        $idsAll = collect($responseAll->json('data'))->pluck('id')->all();
        $this->assertContains($q1->id, $idsAll);
        $this->assertContains($q2->id, $idsAll);
    }

    public function test_user_without_permission_cannot_access_quotations_endpoints(): void
    {
        $this->authenticateWith([]); // Sin permisos

        $quotation = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
        ]);

        $this->getJson(self::BASE)->assertForbidden();
        $this->postJson(self::BASE, $this->defaultPayload())->assertForbidden();
        $this->getJson(self::BASE."/{$quotation->id}")->assertForbidden();
        $this->putJson(self::BASE."/{$quotation->id}", $this->defaultPayload())->assertForbidden();
        $this->getJson(self::BASE."/{$quotation->id}/availability")->assertForbidden();
        $this->get(self::BASE."/{$quotation->id}/pdf")->assertForbidden();
        $this->postJson(self::BASE."/{$quotation->id}/duplicate")->assertForbidden();
        $this->postJson(self::BASE."/{$quotation->id}/send")->assertForbidden();
        $this->postJson(self::BASE."/{$quotation->id}/accept")->assertForbidden();
        $this->postJson(self::BASE."/{$quotation->id}/reject")->assertForbidden();
        $this->postJson(self::BASE."/{$quotation->id}/cancel")->assertForbidden();
    }

    public function test_client_with_quotations_cannot_be_deleted(): void
    {
        $quotation = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
        ]);

        $clientService = app(ClientService::class);

        $this->expectException(ClientRuleException::class);
        $this->expectExceptionMessage('No se puede eliminar un cliente con documentos asociados.');

        $clientService->delete($this->client);
    }

    public function test_employee_seller_with_quotations_cannot_be_deleted(): void
    {
        $quotation = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
            'seller_id' => $this->seller->id,
        ]);

        $employeeService = app(EmployeeService::class);

        $this->expectException(EmployeeRuleException::class);
        $this->expectExceptionMessage('No se puede eliminar un empleado con cotizaciones asociadas.');

        $employeeService->delete($this->seller);
    }

    public function test_catalog_item_with_quotations_cannot_be_deleted(): void
    {
        $quotation = Quotation::factory()->create([
            'branch_id' => $this->branch->id,
            'client_id' => $this->client->id,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'line' => 1,
            'catalog_item_id' => $this->product->id,
            'item_presentation_id' => $this->presentation->id,
            'description' => 'Filtro',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_type' => TaxType::Taxable,
            'line_subtotal' => 100,
            'line_tax' => 18,
            'line_total' => 118,
        ]);

        $catalogItemService = app(CatalogItemService::class);

        $this->expectException(InventoryRuleException::class);
        $this->expectExceptionMessage('No se puede eliminar un ítem incluido en cotizaciones.');

        $catalogItemService->delete($this->product);
    }
}
