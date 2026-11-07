<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Clients\Models\Client;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Enums\CashSessionStatus;
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
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Services\SaleIssuanceService;
use App\Modules\Users\Models\User;
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
use Tests\TestCase;

class ReceivablesReportsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private Warehouse $warehouse;

    private Box $box;

    private CashSession $cashSession;

    private DocumentSeries $seriesFactura;

    private Client $creditClient;

    private Employee $seller;

    private CatalogItem $product;

    private ItemPresentation $presentation;

    private PaymentMethod $cashMethod;

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
        $this->branch = Branch::where('is_main', true)->first() ?? Branch::firstOrFail();
        $this->admin->branches()->syncWithoutDetaching([$this->branch->id => ['is_default' => true]]);

        $this->pen = Currency::where('code', 'PEN')->firstOrFail();

        $this->warehouse = Warehouse::create([
            'branch_id' => $this->branch->id,
            'code' => 'ALM-01',
            'name' => 'Almacén Central',
            'is_active' => true,
        ]);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->box = Box::create([
            'branch_id' => $this->branch->id,
            'code' => 'CJ-01',
            'name' => 'Caja Reportes',
            'is_active' => true,
        ]);
        $this->box->roles()->sync([$adminRole->id]);

        $this->cashSession = CashSession::create([
            'branch_id' => $this->branch->id,
            'box_id' => $this->box->id,
            'opened_by' => $this->admin->id,
            'number' => 'CJ-000001',
            'opened_at' => now(),
            'opening_amount' => 100.00,
            'status' => CashSessionStatus::Open->value,
        ]);

        $this->cashMethod = PaymentMethod::where('code', 'CASH')->first()
            ?? PaymentMethod::where('kind', 'cash')->firstOrFail();

        $this->seriesFactura = DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::Invoice,
            'series' => 'F001',
            'current_number' => 30,
            'is_active' => true,
        ]);

        $this->creditClient = Client::create([
            'document_type' => 'RUC',
            'document_number' => '20608889991',
            'name' => 'CLIENTE REPORTES Y COBRANZAS SAC',
            'address' => 'Av. Arequipa 500',
            'is_active' => true,
        ]);
        $this->creditClient->forceFill([
            'credit_limit' => 30000.00,
            'credit_days' => 60,
        ])->save();

        $this->seller = Employee::first() ?? Employee::create([
            'first_name' => 'Carlos',
            'last_name' => 'Vendedor',
            'document_type' => 'DNI',
            'document_number' => '44556677',
            'email' => 'carlos@example.com',
            'is_active' => true,
        ]);

        $category = Category::first() ?? Category::create(['name' => 'General', 'code' => 'GEN', 'is_active' => true]);
        $unit = Unit::where('symbol', 'UND')->first() ?? Unit::firstOrFail();

        $this->product = CatalogItem::create([
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Producto Reportes Test',
            'code' => 'PROD-REP-01',
            'type' => 'product',
            'tax_type' => TaxType::Taxable,
            'track_stock' => true,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $this->presentation = ItemPresentation::create([
            'catalog_item_id' => $this->product->id,
            'unit_id' => $unit->id,
            'name' => 'Unidad',
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

        /** @var StockLedgerService $stockLedger */
        $stockLedger = app(StockLedgerService::class);
        $stockLedger->in(new StockMovementData(
            warehouseId: $this->warehouse->id,
            catalogItemId: $this->product->id,
            baseQuantity: 100.0,
            type: MovementType::Opening,
            source: $this->warehouse,
            sourceNumber: 'INI-REP',
            unitCost: 50.0,
            notes: 'Stock inicial test reportes',
            movedAt: now(),
        ));

        // Crear una venta al crédito para tener datos en reportes
        /** @var SaleIssuanceService $issuance */
        $issuance = app(SaleIssuanceService::class);
        $issuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'warehouse_id' => $this->warehouse->id,
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 2,
                    'unit_price' => 100.00, // 236.00
                    'tax_type' => TaxType::Taxable->value,
                ],
            ],
        ], $this->admin, $this->branch);
    }

    private function tokenFor(User $user): string
    {
        return app(JwtService::class)->issueAccessToken($user);
    }

    public function test_receivables_index_and_summary(): void
    {
        $token = $this->tokenFor($this->admin);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->getJson('/api/v1/sales/receivables');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'summary',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

        $this->assertNotEmpty($response->json('data'));
        $this->assertNotEmpty($response->json('summary'));
    }

    public function test_receivables_filter_by_status_pending_partially_paid(): void
    {
        $token = $this->tokenFor($this->admin);

        // Con status=pending,partially_paid debe retornar las cuotas con saldo positivo
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->getJson('/api/v1/sales/receivables?status=pending,partially_paid');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertContains($item['status'], ['pending', 'partially_paid']);
            $this->assertGreaterThan(0, (float) $item['balance']);
        }

        // Si filtramos por status=paid no debe retornar cuotas con saldo pendientes
        $paidResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->getJson('/api/v1/sales/receivables?status=paid');

        $paidResponse->assertOk();
        $this->assertEmpty($paidResponse->json('data'));
    }

    public function test_receivables_export_csv(): void
    {
        $token = $this->tokenFor($this->admin);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->get('/api/v1/sales/receivables/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_receivables_aging_report(): void
    {
        $token = $this->tokenFor($this->admin);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->getJson('/api/v1/sales/receivables/aging');

        $response->assertOk();
        $response->assertJsonStructure([
            'as_of',
            'items',
            'totals',
        ]);
    }

    public function test_collections_index_and_export(): void
    {
        $token = $this->tokenFor($this->admin);

        // Registrar cobranza primero
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->postJson('/api/v1/sales/collections', [
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'amount' => 100.00,
            'payment_method_id' => $this->cashMethod->id,
            'cash_session_id' => $this->cashSession->id,
            'auto_allocate' => true,
        ])->assertCreated();

        $indexRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->getJson('/api/v1/sales/collections');

        $indexRes->assertOk();
        $this->assertNotEmpty($indexRes->json('data'));

        $exportRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->get('/api/v1/sales/collections/export');

        $exportRes->assertOk();
        $this->assertStringContainsString('text/csv', (string) $exportRes->headers->get('content-type'));
    }

    public function test_client_statement_json_and_pdf(): void
    {
        $token = $this->tokenFor($this->admin);

        // Estado de cuenta JSON
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/clients/{$this->creditClient->id}/statement");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'client',
                'currency',
                'from',
                'to',
                'opening_balance',
                'total_debits',
                'total_credits',
                'closing_balance',
                'movements',
                'unpaid_installments',
            ],
        ]);

        // Estado de cuenta PDF
        $pdfRes = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get("/api/v1/clients/{$this->creditClient->id}/statement/pdf");

        $pdfRes->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfRes->headers->get('content-type'));
    }
}
