<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Clients\Models\Client;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\ItemPrice;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Roles\Models\Role;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Enums\QuotationStatus;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Events\SaleIssued;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\QuotationItem;
use App\Modules\Users\Models\User;
use App\Support\Identity\DocumentType;
use Database\Seeders\BranchSeeder;
use Database\Seeders\ClientSeeder;
use Database\Seeders\FinanceDemoSeeder;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\InventoryMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private Branch $branch;

    private Branch $otherBranch;

    private Warehouse $warehouse;

    private Box $box;

    private CashSession $cashSession;

    private DocumentSeries $seriesBoleta;

    private DocumentSeries $seriesFactura;

    private Client $genericClient;

    private Client $rucClient;

    private Client $creditClient;

    private Employee $seller;

    private CatalogItem $product;

    private ItemPresentation $presentation;

    private PaymentMethod $cashMethod;

    private PaymentMethod $transferMethod;

    private Currency $pen;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(SystemParameterService::CACHE_KEY);
        $this->seed([
            RolePermissionSeeder::class,
            SystemParameterSeeder::class,
            UserSeeder::class,
            BranchSeeder::class,
            InventoryMastersSeeder::class,
            FinanceMastersSeeder::class,
            FinanceDemoSeeder::class,
            ClientSeeder::class,
        ]);

        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->operator = User::where('email', 'user@example.com')->firstOrFail();
        $this->branch = Branch::where('is_main', true)->first() ?? Branch::firstOrFail();
        $this->otherBranch = Branch::create(['code' => 'SUC-09', 'name' => 'Sede Otra', 'is_main' => false]);

        $this->admin->branches()->syncWithoutDetaching([$this->branch->id => ['is_default' => true], $this->otherBranch->id => ['is_default' => false]]);
        $this->operator->branches()->syncWithoutDetaching([$this->branch->id => ['is_default' => true]]);

        $this->pen = Currency::where('code', 'PEN')->firstOrFail();

        $this->warehouse = Warehouse::create([
            'branch_id' => $this->branch->id,
            'code' => 'ALM-01',
            'name' => 'Almacén Central',
            'is_active' => true,
        ]);

        $userRole = Role::where('name', 'user')->firstOrFail();
        $this->box = Box::create([
            'branch_id' => $this->branch->id,
            'code' => 'CJ-01',
            'name' => 'Caja Mostrador',
            'is_active' => true,
        ]);
        $this->box->roles()->sync([$userRole->id]);

        $this->cashSession = CashSession::create([
            'number' => 'SES-000001',
            'box_id' => $this->box->id,
            'branch_id' => $this->branch->id,
            'status' => CashSessionStatus::Open->value,
            'opened_by' => $this->admin->id,
            'opened_at' => now(),
            'opening_amount' => 100.00,
        ]);

        $this->seriesBoleta = DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::Receipt,
            'series' => 'B001',
            'last_number' => 0,
            'is_electronic' => true,
            'is_active' => true,
        ]);

        $this->seriesFactura = DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::Invoice,
            'series' => 'F001',
            'last_number' => 0,
            'is_electronic' => true,
            'is_active' => true,
        ]);

        $this->genericClient = Client::where('is_system', true)->first() ?? Client::factory()->create([
            'name' => 'CLIENTE VARIOS',
            'is_system' => true,
            'credit_limit' => 0,
        ]);

        $this->rucClient = Client::factory()->create([
            'name' => 'SERVICIOS INDUSTRIALES S.A.C.',
            'document_type' => DocumentType::Ruc,
            'document_number' => '20601234567',
            'is_system' => false,
            'credit_limit' => 0,
        ]);

        $this->creditClient = Client::factory()->create([
            'name' => 'IMPORTACIONES DEL NORTE S.R.L.',
            'document_type' => DocumentType::Ruc,
            'document_number' => '20509876543',
            'is_system' => false,
            'credit_limit' => 5000.00,
            'credit_days' => 30,
        ]);

        $this->seller = Employee::create([
            'branch_id' => $this->branch->id,
            'first_name' => 'Carlos',
            'last_name' => 'Vendedor',
            'document_type' => 'DNI',
            'document_number' => '45678901',
            'email' => 'carlos.vendedor@erp.test',
            'is_active' => true,
        ]);

        $category = Category::first() ?? Category::create(['name' => 'General', 'code' => 'GEN', 'is_active' => true]);
        $unit = Unit::where('symbol', 'UND')->first() ?? Unit::firstOrFail();

        $this->product = CatalogItem::create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Aceite Sintético 5W-30',
            'code' => 'ITM-99001',
            'type' => 'product',
            'tax_type' => TaxType::Taxable,
            'track_stock' => true,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $this->presentation = ItemPresentation::create([
            'catalog_item_id' => $this->product->id,
            'unit_id' => $unit->id,
            'name' => 'Galón',
            'factor' => 1.0,
            'is_base' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        ItemPrice::create([
            'item_presentation_id' => $this->presentation->id,
            'currency_id' => $this->pen->id,
            'price_type' => PriceType::Retail,
            'price' => 100.00,
            'min_quantity' => 1,
        ]);

        // Cargar stock inicial: 50 unidades al costo de 60.00
        app(StockLedgerService::class)->in(new StockMovementData(
            warehouseId: $this->warehouse->id,
            catalogItemId: $this->product->id,
            baseQuantity: 50.0,
            type: MovementType::Opening,
            source: $this->warehouse,
            sourceNumber: 'INIT-001',
            unitCost: 60.0,
            presentationId: $this->presentation->id,
            presentationQuantity: 50.0,
            reasonCode: 'INIT',
            notes: 'Stock inicial de prueba',
        ));

        $this->cashMethod = PaymentMethod::where('code', 'CASH')->firstOrFail();
        $this->transferMethod = PaymentMethod::where('code', 'TRANSFER_BCP')->firstOrFail();

        if ($this->transferMethod->bank_account_id) {
            BankPeriod::firstOrCreate(
                [
                    'bank_account_id' => $this->transferMethod->bank_account_id,
                    'status' => BankPeriodStatus::Open,
                ],
                [
                    'number' => 1,
                    'opened_at' => now()->startOfMonth()->toDateString(),
                    'opening_balance' => '1000.00',
                ]
            );
        }
    }

    private function authAs(User $user): self
    {
        $token = app(JwtService::class)->createTokenPair($user)['access_token'];

        return $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Branch-Id', $this->branch->id);
    }

    public function test_can_issue_cash_receipt_with_multiple_payments(): void
    {
        // Venta de 2 unidades a 100.00 = 200.00 total (incluye IGV)
        // Pago 1: Efectivo 100.00 (recibe 120.00, vuelto 20.00)
        // Pago 2: Transferencia / Banco 100.00
        $response = $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'cash_session_id' => $this->cashSession->id,
                'warehouse_id' => $this->warehouse->id,
                'client_id' => $this->rucClient->id,
                'seller_id' => $this->seller->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 2,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 100.00,
                        'received_amount' => 120.00,
                    ],
                    [
                        'payment_method_id' => $this->transferMethod->id,
                        'amount' => 100.00,
                        'reference' => 'TRF-98765',
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.full_number', 'B001-00000001')
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.total', '200.00')
            ->assertJsonPath('data.paid_amount', '200.00')
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.cost_total', '120.0000') // 2 unid * 60.00 costo prom = 120.00
            ->assertJsonCount(2, 'data.payments');

        // Segunda venta consecutiva para validar correlativo incremental
        $response2 = $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->rucClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 100.00,
                    ],
                ],
            ]);

        $response2->assertCreated()
            ->assertJsonPath('data.full_number', 'B001-00000002');
    }

    public function test_invoice_requires_client_with_valid_ruc(): void
    {
        // Factura con cliente genérico sin RUC -> 422
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesFactura->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->genericClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 100.00,
                    ],
                ],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id']);

        // Factura con cliente RUC -> exitosa
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesFactura->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->rucClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 100.00,
                    ],
                ],
            ])->assertCreated()
            ->assertJsonPath('data.full_number', 'F001-00000001');
    }

    public function test_receipt_over_threshold_requires_identified_client(): void
    {
        // 8 unidades a 100.00 = 800.00 (supera el umbral de 700.00)
        // Con cliente genérico -> 422
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->genericClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 8,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 800.00,
                    ],
                ],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_cash_payments_must_sum_exactly_the_total(): void
    {
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->rucClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 1, // Total 100.00
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 90.00, // Falta 10.00
                    ],
                ],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['payments']);
    }

    public function test_insufficient_stock_fails_and_does_not_consume_correlative(): void
    {
        $lastNumberBefore = $this->seriesBoleta->fresh()->last_number;

        // Intentar vender 100 unidades cuando solo hay 50
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->rucClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 100,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 10000.00,
                    ],
                ],
            ])->assertStatus(422);

        // El correlativo de la serie no debe haberse consumido
        $this->assertSame($lastNumberBefore, $this->seriesBoleta->fresh()->last_number);
    }

    public function test_discount_exceeding_threshold_without_override_fails(): void
    {
        // Operador sin sales.discount_override intentando 50% de descuento
        $this->authAs($this->operator)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->rucClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 1,
                        'discount_percent' => 50,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 50.00,
                    ],
                ],
            ])->assertStatus(422);
    }

    public function test_can_sell_on_credit_with_advance_and_dispatches_sale_issued_event(): void
    {
        Event::fake([SaleIssued::class]);

        // Venta al crédito a cliente con límite de 5000:
        // Total 200.00, pago inicial (adelanto) 50.00, saldo 150.00
        $response = $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->creditClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Credit->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 2,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 50.00,
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_condition', 'credit')
            ->assertJsonPath('data.total', '200.00')
            ->assertJsonPath('data.paid_amount', '50.00')
            ->assertJsonPath('data.balance', '150.00');

        Event::assertDispatched(SaleIssued::class);
    }

    public function test_sale_on_credit_exceeding_available_limit_fails(): void
    {
        // Cliente con límite 5000: intentamos vender 60 unidades a 100 = 6000
        // Cargar stock suficiente primero
        app(StockLedgerService::class)->in(new StockMovementData(
            warehouseId: $this->warehouse->id,
            catalogItemId: $this->product->id,
            baseQuantity: 100.0,
            type: MovementType::Opening,
            source: $this->warehouse,
            sourceNumber: 'INIT-002',
            unitCost: 60.0,
            presentationId: $this->presentation->id,
            presentationQuantity: 100.0,
        ));

        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'client_id' => $this->creditClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Credit->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 60,
                    ],
                ],
                'payments' => [],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_condition']);
    }

    public function test_converting_quotation_marks_it_converted(): void
    {
        $quotation = Quotation::create([
            'number' => 'COT-000001',
            'branch_id' => $this->branch->id,
            'client_id' => $this->rucClient->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'currency_id' => $this->pen->id,
            'price_type' => PriceType::Retail,
            'prices_include_tax' => true,
            'tax_rate' => 18.00,
            'total' => 100.00,
            'total_base_currency' => 100.00,
            'status' => QuotationStatus::Accepted,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'line' => 1,
            'catalog_item_id' => $this->product->id,
            'item_presentation_id' => $this->presentation->id,
            'description' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 100.00,
            'tax_type' => TaxType::Taxable,
            'line_subtotal' => 84.75,
            'line_tax' => 15.25,
            'line_total' => 100.00,
        ]);

        $response = $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->rucClient->id,
                'currency_id' => $this->pen->id,
                'quotation_id' => $quotation->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 100.00,
                    ],
                ],
            ]);

        $response->assertCreated();

        $freshQuotation = $quotation->fresh();
        $this->assertSame(QuotationStatus::Converted, $freshQuotation->status);
        $this->assertNotNull($freshQuotation->converted_sale_id);
    }

    public function test_prefill_without_quotation_id_returns_422_instead_of_crashing(): void
    {
        $response = $this->authAs($this->admin)->getJson('/api/v1/sales/sales/prefill');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quotation_id']);
    }

    public function test_prefill_with_empty_quotation_id_returns_422_instead_of_crashing(): void
    {
        $response = $this->authAs($this->admin)->getJson('/api/v1/sales/sales/prefill?quotation_id=');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quotation_id']);
    }

    public function test_prefill_with_non_uuid_quotation_id_returns_422_instead_of_crashing(): void
    {
        $response = $this->authAs($this->admin)->getJson('/api/v1/sales/sales/prefill?quotation_id=not-a-uuid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quotation_id']);
    }

    public function test_prefill_loads_quotation_data_for_the_pos_cart(): void
    {
        $quotation = Quotation::create([
            'number' => 'COT-000002',
            'branch_id' => $this->branch->id,
            'client_id' => $this->rucClient->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'currency_id' => $this->pen->id,
            'price_type' => PriceType::Retail,
            'prices_include_tax' => true,
            'tax_rate' => 18.00,
            'total' => 100.00,
            'total_base_currency' => 100.00,
            'status' => QuotationStatus::Accepted,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'line' => 1,
            'catalog_item_id' => $this->product->id,
            'item_presentation_id' => $this->presentation->id,
            'description' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 100.00,
            'tax_type' => TaxType::Taxable,
            'line_subtotal' => 84.75,
            'line_tax' => 15.25,
            'line_total' => 100.00,
        ]);

        $response = $this->authAs($this->admin)
            ->getJson("/api/v1/sales/sales/prefill?quotation_id={$quotation->id}");

        $response->assertOk()
            ->assertJsonPath('data.quotation_id', $quotation->id)
            ->assertJsonPath('data.client_id', $this->rucClient->id)
            ->assertJsonPath('data.items.0.catalog_item_id', $this->product->id);
    }

    public function test_prefill_rejects_quotation_from_another_branch(): void
    {
        $quotation = Quotation::create([
            'number' => 'COT-000003',
            'branch_id' => $this->otherBranch->id,
            'client_id' => $this->rucClient->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'currency_id' => $this->pen->id,
            'price_type' => PriceType::Retail,
            'prices_include_tax' => true,
            'tax_rate' => 18.00,
            'total' => 100.00,
            'total_base_currency' => 100.00,
            'status' => QuotationStatus::Accepted,
        ]);

        $response = $this->authAs($this->admin)
            ->getJson("/api/v1/sales/sales/prefill?quotation_id={$quotation->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quotation_id']);
    }

    public function test_closed_cash_session_is_rejected(): void
    {
        $this->cashSession->update(['status' => CashSessionStatus::Closed->value]);

        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->rucClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 100.00,
                    ],
                ],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['cash_session_id']);
    }

    public function test_series_from_another_branch_is_rejected(): void
    {
        $otherSeries = DocumentSeries::create([
            'branch_id' => $this->otherBranch->id,
            'document_type' => SalesDocumentType::Receipt,
            'series' => 'B009',
        ]);

        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $otherSeries->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->rucClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 100.00,
                    ],
                ],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['document_series_id']);
    }

    public function test_sales_listing_returns_meta_totals_and_respects_permissions(): void
    {
        // Emitir una venta con admin
        $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->rucClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 100.00,
                    ],
                ],
            ])->assertCreated();

        // 1. Admin consulta con sales.view_all y sales.view_costs
        $response = $this->authAs($this->admin)->getJson('/api/v1/sales/sales');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.totals.total', '100.00')
            ->assertJsonPath('meta.totals.tax_total', '15.25')
            ->assertJsonPath('meta.totals.cost_total', '60.00')
            ->assertJsonPath('meta.totals.margin_total', '40.00');

        // 2. Operador consulta (no tiene sales.view_costs -> cost_total y margin_total en null)
        $responseOp = $this->authAs($this->operator)->getJson('/api/v1/sales/sales');
        $responseOp->assertOk()
            ->assertJsonPath('meta.totals.cost_total', null)
            ->assertJsonPath('meta.totals.margin_total', null);
    }

    public function test_can_generate_pdf_in_a4_and_ticket_formats(): void
    {
        $res = $this->authAs($this->admin)
            ->postJson('/api/v1/sales/sales', [
                'document_series_id' => $this->seriesBoleta->id,
                'cash_session_id' => $this->cashSession->id,
                'client_id' => $this->rucClient->id,
                'currency_id' => $this->pen->id,
                'payment_condition' => PaymentCondition::Cash->value,
                'items' => [
                    [
                        'item_presentation_id' => $this->presentation->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method_id' => $this->cashMethod->id,
                        'amount' => 100.00,
                    ],
                ],
            ]);

        $saleId = $res->json('data.id');

        // Formato A4
        $this->authAs($this->admin)
            ->getJson("/api/v1/sales/sales/{$saleId}/pdf?format=a4&inline=1")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        // Formato Ticket
        $this->authAs($this->admin)
            ->getJson("/api/v1/sales/sales/{$saleId}/pdf?format=ticket&inline=1")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
