<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Audit\Models\Activity;
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
use App\Modules\Sales\EInvoicing\EInvoiceResult;
use App\Modules\Sales\EInvoicing\EInvoicingManager;
use App\Modules\Sales\EInvoicing\ElectronicInvoicingDriver;
use App\Modules\Sales\Enums\EInvoiceStatus;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Jobs\SendElectronicDocumentJob;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Models\Sale;
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
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SendElectronicDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private Warehouse $warehouse;

    private Box $box;

    private CashSession $cashSession;

    private DocumentSeries $seriesBoleta;

    private Client $genericClient;

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
            'current_number' => 0,
            'is_electronic' => true,
            'is_active' => true,
        ]);

        $this->genericClient = Client::firstOrCreate(
            ['document_number' => '00000000'],
            [
                'name' => 'CLIENTE GENERAL',
                'is_system' => true,
                'is_active' => true,
            ]
        );

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
            notes: 'Stock inicial',
        ));

        $this->cashMethod = PaymentMethod::where('code', 'CASH')->firstOrFail();
    }

    private function authAs(User $user): self
    {
        $token = app(JwtService::class)->createTokenPair($user)['access_token'];

        return $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Branch-Id', $this->branch->id);
    }

    private function issueSampleSale(): Sale
    {
        return app(SaleIssuanceService::class)->issue([
            'document_series_id' => $this->seriesBoleta->id,
            'client_id' => $this->genericClient->id,
            'seller_id' => $this->seller->id,
            'warehouse_id' => $this->warehouse->id,
            'cash_session_id' => $this->cashSession->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Cash->value,
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $this->cashMethod->id,
                    'amount' => 100.00,
                    'received_amount' => 100.00,
                ],
            ],
        ], $this->admin, $this->branch);
    }

    public function test_job_updates_sale_with_null_driver(): void
    {
        Queue::fake();

        $sale = $this->issueSampleSale();

        $job = new SendElectronicDocumentJob($sale->id);
        $job->handle(app(EInvoicingManager::class));

        $fresh = $sale->fresh();
        $this->assertSame(EInvoiceStatus::NotApplicable, $fresh->einvoice_status);
        $this->assertSame(1, $fresh->einvoice_attempts);
        $this->assertNotNull($fresh->einvoice_sent_at);
        $this->assertIsArray($fresh->einvoice_response);
        $this->assertSame('0', $fresh->einvoice_response['code'] ?? null);
    }

    public function test_job_marks_sale_accepted_when_driver_returns_accepted(): void
    {
        Queue::fake();

        $sale = $this->issueSampleSale();

        $mockDriver = Mockery::mock(ElectronicInvoicingDriver::class);
        $mockDriver->shouldReceive('send')
            ->once()
            ->andReturn(new EInvoiceResult(
                status: EInvoiceStatus::Accepted,
                code: '0',
                message: 'La Factura ha sido aceptada',
                hash: 'abc123hash'
            ));

        $manager = Mockery::mock(EInvoicingManager::class);
        $manager->shouldReceive('driver')->once()->andReturn($mockDriver);

        $job = new SendElectronicDocumentJob($sale->id);
        $job->handle($manager);

        $fresh = $sale->fresh();
        $this->assertSame(EInvoiceStatus::Accepted, $fresh->einvoice_status);
        $this->assertSame(1, $fresh->einvoice_attempts);
        $this->assertSame('abc123hash', $fresh->einvoice_response['hash'] ?? null);
    }

    public function test_job_marks_activity_log_as_system_action_without_human_causer(): void
    {
        Queue::fake();

        $sale = $this->issueSampleSale();

        $mockDriver = Mockery::mock(ElectronicInvoicingDriver::class);
        $mockDriver->shouldReceive('send')
            ->once()
            ->andReturn(new EInvoiceResult(
                status: EInvoiceStatus::Accepted,
                code: '0',
                message: 'La Factura ha sido aceptada',
                hash: 'abc123hash'
            ));

        $manager = Mockery::mock(EInvoicingManager::class);
        $manager->shouldReceive('driver')->once()->andReturn($mockDriver);

        $job = new SendElectronicDocumentJob($sale->id);
        $job->handle($manager);

        /** @var Activity $log */
        $log = Activity::query()
            ->where('subject_type', $sale->getMorphClass())
            ->where('subject_id', $sale->id)
            ->where('event', 'updated')
            ->latest('created_at')
            ->firstOrFail();

        $this->assertNull($log->causer_id);
        $this->assertTrue($log->properties['system'] ?? false);
        // branch_id se resuelve del propio Sale (no requiere contexto de request en el worker)
        $this->assertSame($sale->branch_id, $log->branch_id);
    }

    public function test_job_marks_sale_rejected_when_driver_returns_rejected(): void
    {
        $sale = $this->issueSampleSale();
        $sale->update(['einvoice_status' => EInvoiceStatus::Pending, 'einvoice_attempts' => 0]);

        $mockDriver = Mockery::mock(ElectronicInvoicingDriver::class);
        $mockDriver->shouldReceive('send')
            ->once()
            ->andReturn(new EInvoiceResult(
                status: EInvoiceStatus::Rejected,
                code: '2001',
                message: 'El comprobante ya fue registrado con anterioridad'
            ));

        $manager = Mockery::mock(EInvoicingManager::class);
        $manager->shouldReceive('driver')->once()->andReturn($mockDriver);

        $job = new SendElectronicDocumentJob($sale->id);
        $job->handle($manager);

        $fresh = $sale->fresh();
        $this->assertSame(EInvoiceStatus::Rejected, $fresh->einvoice_status);
        $this->assertSame('2001', $fresh->einvoice_response['code'] ?? null);
    }

    public function test_job_handles_exception_and_marks_error(): void
    {
        $sale = $this->issueSampleSale();
        $sale->update(['einvoice_status' => EInvoiceStatus::Pending, 'einvoice_attempts' => 0]);

        $mockDriver = Mockery::mock(ElectronicInvoicingDriver::class);
        $mockDriver->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Connection timeout to PSE'));

        $manager = Mockery::mock(EInvoicingManager::class);
        $manager->shouldReceive('driver')->once()->andReturn($mockDriver);

        $job = new SendElectronicDocumentJob($sale->id);
        $job->handle($manager);

        $fresh = $sale->fresh();
        $this->assertSame(EInvoiceStatus::Error, $fresh->einvoice_status);
        $this->assertSame('EXCEPTION', $fresh->einvoice_response['code'] ?? null);
        $this->assertStringContainsString('Connection timeout to PSE', $fresh->einvoice_response['message'] ?? '');
    }

    public function test_job_ignores_cancelled_sale(): void
    {
        $sale = $this->issueSampleSale();
        $sale->update(['status' => SaleStatus::Cancelled, 'einvoice_attempts' => 1]);

        $manager = Mockery::mock(EInvoicingManager::class);
        $manager->shouldNotReceive('driver');

        $job = new SendElectronicDocumentJob($sale->id);
        $job->handle($manager);

        // Attempt count did not increment
        $this->assertSame(1, $sale->fresh()->einvoice_attempts);
    }

    public function test_retry_einvoice_endpoint_enqueues_job(): void
    {
        $sale = $this->issueSampleSale();
        $sale->update(['einvoice_status' => EInvoiceStatus::Error]);

        Queue::fake();

        $response = $this->authAs($this->admin)
            ->postJson("/api/v1/sales/sales/{$sale->id}/einvoice/retry");

        $response->assertStatus(202)
            ->assertJsonPath('message', 'Envío de comprobante encolado correctamente.');

        Queue::assertPushed(SendElectronicDocumentJob::class, fn (SendElectronicDocumentJob $job) => $job->saleId === $sale->id);
    }

    public function test_retry_einvoice_rejected_if_already_accepted(): void
    {
        $sale = $this->issueSampleSale();
        $sale->update(['einvoice_status' => EInvoiceStatus::Accepted]);

        Queue::fake();

        $response = $this->authAs($this->admin)
            ->postJson("/api/v1/sales/sales/{$sale->id}/einvoice/retry");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Solo se pueden reintentar comprobantes en estado de error o rechazados.');

        Queue::assertNotPushed(SendElectronicDocumentJob::class);
    }
}
