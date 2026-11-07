<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Clients\Models\Client;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Models\Currency;
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
use App\Modules\Sales\Enums\InstallmentStatus;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Models\ReceivableInstallment;
use App\Modules\Sales\Services\SaleCancellationService;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReceivableInstallmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private Branch $branch;

    private Warehouse $warehouse;

    private DocumentSeries $seriesFactura;

    private Client $creditClient;

    private Employee $seller;

    private CatalogItem $product;

    private ItemPresentation $presentation;

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

        $this->admin->branches()->syncWithoutDetaching([$this->branch->id => ['is_default' => true]]);
        $this->operator->branches()->syncWithoutDetaching([$this->branch->id => ['is_default' => true]]);

        $this->pen = Currency::where('code', 'PEN')->firstOrFail();

        $this->warehouse = Warehouse::create([
            'branch_id' => $this->branch->id,
            'code' => 'ALM-01',
            'name' => 'Almacén Central',
            'is_active' => true,
        ]);

        $this->seriesFactura = DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::Invoice,
            'series' => 'F001',
            'current_number' => 10,
            'is_active' => true,
        ]);

        $this->creditClient = Client::create([
            'document_type' => 'RUC',
            'document_number' => '20601234567',
            'name' => 'EMPRESA INDUSTRIAL SAC',
            'address' => 'Av. Industrial 123',
            'is_active' => true,
        ]);
        $this->creditClient->forceFill([
            'credit_limit' => 10000.00,
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
            'name' => 'Producto Crédito Test',
            'code' => 'PROD-CRED-01',
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
            sourceNumber: 'INI-001',
            unitCost: 50.0,
            notes: 'Stock inicial test crédito',
            movedAt: now(),
        ));
    }

    private function tokenFor(User $user): string
    {
        return app(JwtService::class)->issueAccessToken($user);
    }

    public function test_credit_sale_without_explicit_installments_creates_single_installment(): void
    {
        /** @var SaleIssuanceService $issuance */
        $issuance = app(SaleIssuanceService::class);

        $sale = $issuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'warehouse_id' => $this->warehouse->id,
            'client_id' => $this->creditClient->id,
            'seller_id' => $this->seller->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'tax_type' => TaxType::Taxable->value,
                ],
            ],
        ], $this->admin, $this->branch);

        $this->assertEquals(SaleStatus::Issued, $sale->status);
        $this->assertEquals(PaymentCondition::Credit, $sale->payment_condition);

        $installments = $sale->installments;
        $this->assertCount(1, $installments);

        $inst = $installments->first();
        $this->assertNotNull($inst);
        $this->assertEquals(1, $inst->number);
        $this->assertEquals('200.00', (string) $inst->amount);
        $this->assertEquals('200.00', (string) $inst->balance);
        $this->assertEquals('0.00', (string) $inst->paid_amount);
        $this->assertEquals(InstallmentStatus::Pending, $inst->status);
    }

    public function test_credit_sale_with_count_and_interval_generates_equal_installments(): void
    {
        /** @var SaleIssuanceService $issuance */
        $issuance = app(SaleIssuanceService::class);

        $sale = $issuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'warehouse_id' => $this->warehouse->id,
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'installments_count' => 3,
            'installment_interval_days' => 15,
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 1,
                    'unit_price' => 300.00, // Total con IGV: 354.00 => 354 / 3 = 118
                    'tax_type' => TaxType::Taxable->value,
                ],
            ],
        ], $this->admin, $this->branch);

        $installments = $sale->installments()->orderBy('number')->get();
        $this->assertCount(3, $installments);

        $this->assertEquals('100.00', (string) $installments[0]->amount);
        $this->assertEquals('100.00', (string) $installments[1]->amount);
        $this->assertEquals('100.00', (string) $installments[2]->amount);
        $this->assertEquals(1, $installments[0]->number);
        $this->assertEquals(2, $installments[1]->number);
        $this->assertEquals(3, $installments[2]->number);
    }

    public function test_credit_sale_with_explicit_schedule(): void
    {
        /** @var SaleIssuanceService $issuance */
        $issuance = app(SaleIssuanceService::class);

        $sale = $issuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'warehouse_id' => $this->warehouse->id,
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'installments' => [
                ['due_date' => now()->addDays(15)->toDateString(), 'amount' => 80.00],
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => 120.00],
            ],
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 2,
                    'unit_price' => 100.00, // Total: 200.00
                    'tax_type' => TaxType::Taxable->value,
                ],
            ],
        ], $this->admin, $this->branch);

        $installments = $sale->installments()->orderBy('number')->get();
        $this->assertCount(2, $installments);
        $this->assertEquals('80.00', (string) $installments[0]->amount);
        $this->assertEquals('120.00', (string) $installments[1]->amount);
    }

    public function test_explicit_schedule_sum_mismatch_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        /** @var SaleIssuanceService $issuance */
        $issuance = app(SaleIssuanceService::class);

        $issuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'warehouse_id' => $this->warehouse->id,
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'installments' => [
                ['due_date' => now()->addDays(15)->toDateString(), 'amount' => 50.00],
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => 50.00], // Suma 100 != 236
            ],
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'tax_type' => TaxType::Taxable->value,
                ],
            ],
        ], $this->admin, $this->branch);
    }

    public function test_sale_cancellation_marks_all_installments_as_cancelled(): void
    {
        /** @var SaleIssuanceService $issuance */
        $issuance = app(SaleIssuanceService::class);

        $sale = $issuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'warehouse_id' => $this->warehouse->id,
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'tax_type' => TaxType::Taxable->value,
                ],
            ],
        ], $this->admin, $this->branch);

        $this->assertDatabaseHas('receivable_installments', [
            'sale_id' => $sale->id,
            'status' => InstallmentStatus::Pending->value,
        ]);

        /** @var SaleCancellationService $cancellation */
        $cancellation = app(SaleCancellationService::class);
        $cancellation->cancel($sale, 'Anulación de prueba de cuotas', $this->admin);

        $this->assertDatabaseHas('receivable_installments', [
            'sale_id' => $sale->id,
            'status' => InstallmentStatus::Cancelled->value,
        ]);
    }

    public function test_reschedule_installments_api(): void
    {
        /** @var SaleIssuanceService $issuance */
        $issuance = app(SaleIssuanceService::class);

        $sale = $issuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'warehouse_id' => $this->warehouse->id,
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 2,
                    'unit_price' => 100.00, // Total: 236.00
                    'tax_type' => TaxType::Taxable->value,
                ],
            ],
        ], $this->admin, $this->branch);

        $token = $this->tokenFor($this->admin);

        // Reprogramar en 2 cuotas de 100 cada una
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->postJson("/api/v1/sales/sales/{$sale->id}/reschedule", [
            'installments' => [
                ['due_date' => now()->addDays(20)->toDateString(), 'amount' => 100.00],
                ['due_date' => now()->addDays(40)->toDateString(), 'amount' => 100.00],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(2, $response->json('installments'));

        $this->assertDatabaseCount('receivable_installments', 2);
    }

    public function test_credit_sale_is_blocked_when_client_has_overdue_installments(): void
    {
        // 1. Crear una venta anterior y marcar su cuota como vencida
        /** @var SaleIssuanceService $issuance */
        $issuance = app(SaleIssuanceService::class);

        $priorSale = $issuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'warehouse_id' => $this->warehouse->id,
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'tax_type' => TaxType::Taxable->value,
                ],
            ],
        ], $this->admin, $this->branch);

        // Forzar vencimiento anterior a hoy
        ReceivableInstallment::where('sale_id', $priorSale->id)->update([
            'due_date' => Carbon::today()->subDays(5)->toDateString(),
        ]);

        // 2. Intentar una nueva venta al crédito para el mismo cliente
        $this->expectException(ValidationException::class);

        $issuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'warehouse_id' => $this->warehouse->id,
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 1,
                    'unit_price' => 50.00,
                    'tax_type' => TaxType::Taxable->value,
                ],
            ],
        ], $this->admin, $this->branch);
    }
}
