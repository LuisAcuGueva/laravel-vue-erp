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
use App\Modules\Sales\Enums\CollectionStatus;
use App\Modules\Sales\Enums\InstallmentStatus;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Models\Sale;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CollectionsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

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
        $this->unauthorizedUser = User::create([
            'name' => 'Sin Permisos',
            'email' => 'no_perm@example.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->branch = Branch::where('is_main', true)->first() ?? Branch::firstOrFail();

        $this->admin->branches()->syncWithoutDetaching([$this->branch->id => ['is_default' => true]]);
        $this->unauthorizedUser->branches()->syncWithoutDetaching([$this->branch->id => ['is_default' => true]]);

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
            'name' => 'Caja Principal Cobranzas',
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
            'current_number' => 20,
            'is_active' => true,
        ]);

        $this->creditClient = Client::create([
            'document_type' => 'RUC',
            'document_number' => '20609876543',
            'name' => 'CLIENTE COBRANZAS CORP SAC',
            'address' => 'Av. Javier Prado 1000',
            'is_active' => true,
        ]);
        $this->creditClient->forceFill([
            'credit_limit' => 20000.00,
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
            'name' => 'Producto Cobranzas Test',
            'code' => 'PROD-COB-01',
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
            sourceNumber: 'INI-COB',
            unitCost: 50.0,
            notes: 'Stock inicial test cobranzas',
            movedAt: now(),
        ));
    }

    private function tokenFor(User $user): string
    {
        return app(JwtService::class)->issueAccessToken($user);
    }

    private function createCreditSale(float $unitPrice = 100.00, int $quantity = 1): Sale
    {
        /** @var SaleIssuanceService $issuance */
        $issuance = app(SaleIssuanceService::class);

        return $issuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'warehouse_id' => $this->warehouse->id,
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_type' => TaxType::Taxable->value,
                ],
            ],
        ], $this->admin, $this->branch);
    }

    public function test_forbidden_without_permissions(): void
    {
        $token = $this->tokenFor($this->unauthorizedUser);

        // 1. Crear cobranza sin collections.create -> 403
        $resCreate = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->postJson('/api/v1/sales/collections', [
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'amount' => 100.00,
            'payment_method_id' => $this->cashMethod->id,
        ]);
        $resCreate->assertForbidden();

        // 2. Anular cobranza sin collections.cancel -> 403
        $fakeUuid = '00000000-0000-0000-0000-000000000001';
        $resCancel = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->postJson("/api/v1/sales/collections/{$fakeUuid}/cancel", [
            'reason' => 'Sin permisos para anular',
        ]);
        $resCancel->assertForbidden();

        // 3. Reprogramar cuotas sin receivables.reschedule -> 403
        $resReschedule = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->postJson("/api/v1/sales/sales/{$fakeUuid}/reschedule", [
            'installments' => [['due_date' => now()->toDateString(), 'amount' => 100]],
        ]);
        $resReschedule->assertForbidden();
    }

    public function test_create_collection_with_manual_allocation_and_cash_movement(): void
    {
        $sale = $this->createCreditSale(unitPrice: 100.00); // Total 118.00
        $installment = $sale->installments()->firstOrFail();

        $token = $this->tokenFor($this->admin);

        // Amortizar 50.00
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->postJson('/api/v1/sales/collections', [
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'amount' => 50.00,
            'payment_method_id' => $this->cashMethod->id,
            'cash_session_id' => $this->cashSession->id,
            'allocations' => [
                [
                    'receivable_installment_id' => $installment->id,
                    'amount' => 50.00,
                ],
            ],
        ]);

        $response->assertCreated();
        $data = $response->json('data');
        $this->assertEquals('50.00', $data['amount']);
        $this->assertEquals(CollectionStatus::Completed->value, $data['status']);

        // Verificar cuota
        $installment->refresh();
        $this->assertEquals('50.00', (string) $installment->paid_amount);
        $this->assertEquals('50.00', (string) $installment->balance);
        $this->assertEquals(InstallmentStatus::PartiallyPaid, $installment->status);

        // Verificar venta
        $sale->refresh();
        $this->assertEquals('50.00', (string) $sale->paid_amount);
        $this->assertEquals('50.00', (string) $sale->balance);

        // Verificar movimiento en caja
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $this->cashSession->id,
            'amount' => '50.00',
            'direction' => 'in',
            'source_type' => 'collection',
            'source_id' => $data['id'],
        ]);
    }

    public function test_create_collection_with_auto_allocate(): void
    {
        $sale1 = $this->createCreditSale(unitPrice: 50.00); // 59.00
        $sale2 = $this->createCreditSale(unitPrice: 100.00); // 118.00

        $inst1 = $sale1->installments()->firstOrFail();
        $inst2 = $sale2->installments()->firstOrFail();

        $inst1->update(['due_date' => now()->addDays(5)->toDateString()]);
        $inst2->update(['due_date' => now()->addDays(15)->toDateString()]);

        $token = $this->tokenFor($this->admin);

        // Cobrar 80.00 con auto_allocate:true => debe liquidar inst1 (50.00) y amortizar 30.00 en inst2
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->postJson('/api/v1/sales/collections', [
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'amount' => 80.00,
            'payment_method_id' => $this->cashMethod->id,
            'cash_session_id' => $this->cashSession->id,
            'auto_allocate' => true,
        ]);

        $response->assertCreated();

        $inst1->refresh();
        $this->assertEquals('50.00', (string) $inst1->paid_amount);
        $this->assertEquals('0.00', (string) $inst1->balance);
        $this->assertEquals(InstallmentStatus::Paid, $inst1->status);

        $inst2->refresh();
        $this->assertEquals('30.00', (string) $inst2->paid_amount);
        $this->assertEquals('70.00', (string) $inst2->balance);
        $this->assertEquals(InstallmentStatus::PartiallyPaid, $inst2->status);
    }

    public function test_cancel_collection_reverts_balances_and_unsettles_money(): void
    {
        $sale = $this->createCreditSale(unitPrice: 100.00); // 118.00
        $inst = $sale->installments()->firstOrFail();

        $token = $this->tokenFor($this->admin);

        $resCreate = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->postJson('/api/v1/sales/collections', [
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'amount' => 100.00,
            'payment_method_id' => $this->cashMethod->id,
            'cash_session_id' => $this->cashSession->id,
            'auto_allocate' => true,
        ]);

        $resCreate->assertCreated();
        $collectionId = $resCreate->json('data.id');

        $inst->refresh();
        $this->assertEquals(InstallmentStatus::Paid, $inst->status);
        $this->assertEquals('0.00', (string) $inst->balance);

        // Anular cobranza
        $resCancel = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->postJson("/api/v1/sales/collections/{$collectionId}/cancel", [
            'reason' => 'Error de digitación en el monto cobrado',
        ]);

        $resCancel->assertOk();
        $this->assertEquals(CollectionStatus::Cancelled->value, $resCancel->json('data.status'));

        // Saldo restituido en cuota y venta
        $inst->refresh();
        $this->assertEquals('0.00', (string) $inst->paid_amount);
        $this->assertEquals('100.00', (string) $inst->balance);
        $this->assertEquals(InstallmentStatus::Pending, $inst->status);

        $sale->refresh();
        $this->assertEquals('0.00', (string) $sale->paid_amount);
        $this->assertEquals('100.00', (string) $sale->balance);
    }

    public function test_sale_cannot_be_cancelled_if_has_collections(): void
    {
        $sale = $this->createCreditSale(unitPrice: 100.00);

        $token = $this->tokenFor($this->admin);

        // Registrar cobro parcial
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->postJson('/api/v1/sales/collections', [
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'amount' => 50.00,
            'payment_method_id' => $this->cashMethod->id,
            'cash_session_id' => $this->cashSession->id,
            'auto_allocate' => true,
        ])->assertCreated();

        // Intentar anular la venta
        $this->expectException(ValidationException::class);
        /** @var SaleCancellationService $cancellation */
        $cancellation = app(SaleCancellationService::class);
        $cancellation->cancel($sale, 'Intento de anulación indebida', $this->admin);
    }

    public function test_collection_receipt_pdf(): void
    {
        $sale = $this->createCreditSale(unitPrice: 100.00);

        $token = $this->tokenFor($this->admin);

        $res = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->postJson('/api/v1/sales/collections', [
            'client_id' => $this->creditClient->id,
            'currency_id' => $this->pen->id,
            'amount' => 100.00,
            'payment_method_id' => $this->cashMethod->id,
            'cash_session_id' => $this->cashSession->id,
            'auto_allocate' => true,
        ]);

        $res->assertCreated();
        $collectionId = $res->json('data.id');

        $pdfResponse = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Branch-Id' => $this->branch->id,
        ])->get("/api/v1/sales/collections/{$collectionId}/pdf");

        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('content-type'));
    }
}
