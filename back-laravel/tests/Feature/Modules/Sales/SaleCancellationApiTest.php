<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Clients\Models\Client;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashMovement;
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
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Roles\Models\Role;
use App\Modules\Sales\Enums\EInvoiceStatus;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Enums\QuotationStatus;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Events\SaleCancelled;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Models\Quotation;
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
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SaleCancellationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

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
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $this->cashMethod->id,
                    'amount' => 200.00,
                    'received_amount' => 200.00,
                ],
            ],
        ], $this->admin, $this->branch);
    }

    public function test_can_cancel_issued_sale_reversing_kardex_and_cash(): void
    {
        Event::fake([SaleCancelled::class]);

        $sale = $this->issueSampleSale();
        $salePayment = $sale->payments()->firstOrFail();

        $this->assertSame(SaleStatus::Issued, $sale->status);
        $this->assertTrue(KardexMovement::where('source_id', $sale->id)->whereNull('reverses_id')->exists());
        $this->assertTrue(CashMovement::where('source_id', $salePayment->id)->whereNull('reverses_id')->exists());

        $response = $this->authAs($this->admin)
            ->postJson("/api/v1/sales/sales/{$sale->id}/cancel", [
                'reason' => 'Error de digitación por parte del cajero',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', SaleStatus::Cancelled->value)
            ->assertJsonPath('data.cancel_reason', 'Error de digitación por parte del cajero');

        $fresh = $sale->fresh();
        $this->assertSame(SaleStatus::Cancelled, $fresh->status);
        $this->assertSame($this->admin->id, $fresh->cancelled_by);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertSame('Error de digitación por parte del cajero', $fresh->cancel_reason);

        // Kardex reversal exists
        $reversalKardex = KardexMovement::whereNotNull('reverses_id')->first();
        $this->assertNotNull($reversalKardex);

        // Cash reversal exists
        $reversalCash = CashMovement::where('source_id', $salePayment->id)->whereNotNull('reverses_id')->first();
        $this->assertNotNull($reversalCash);

        Event::assertDispatched(SaleCancelled::class, fn (SaleCancelled $e) => $e->sale->id === $sale->id);
    }

    public function test_cannot_cancel_already_cancelled_sale(): void
    {
        $sale = $this->issueSampleSale();

        $this->authAs($this->admin)
            ->postJson("/api/v1/sales/sales/{$sale->id}/cancel", ['reason' => 'Primera anulación'])
            ->assertOk();

        $response = $this->authAs($this->admin)
            ->postJson("/api/v1/sales/sales/{$sale->id}/cancel", ['reason' => 'Segunda anulación']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cannot_cancel_sale_when_cash_session_is_closed(): void
    {
        $sale = $this->issueSampleSale();

        // Cerrar la sesión de caja
        $this->cashSession->update([
            'status' => CashSessionStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $this->admin->id,
        ]);

        $response = $this->authAs($this->admin)
            ->postJson("/api/v1/sales/sales/{$sale->id}/cancel", [
                'reason' => 'Intento con sesión cerrada',
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_cancel_sale_accepted_by_sunat(): void
    {
        $sale = $this->issueSampleSale();
        $sale->update(['einvoice_status' => EInvoiceStatus::Accepted]);

        $response = $this->authAs($this->admin)
            ->postJson("/api/v1/sales/sales/{$sale->id}/cancel", [
                'reason' => 'Intento con comprobante aceptado por SUNAT',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cancellation_reverts_converted_quotation_to_accepted(): void
    {
        $quotation = Quotation::create([
            'branch_id' => $this->branch->id,
            'number' => 'COT-000001',
            'client_id' => $this->genericClient->id,
            'currency_id' => $this->pen->id,
            'status' => QuotationStatus::Accepted,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(7)->toDateString(),
            'tax_rate' => 18.00,
            'total' => 200.00,
            'taxable_base' => 169.49,
            'tax_total' => 30.51,
            'created_by' => $this->admin->id,
        ]);

        $sale = app(SaleIssuanceService::class)->issue([
            'document_series_id' => $this->seriesBoleta->id,
            'client_id' => $this->genericClient->id,
            'seller_id' => $this->seller->id,
            'warehouse_id' => $this->warehouse->id,
            'cash_session_id' => $this->cashSession->id,
            'currency_id' => $this->pen->id,
            'quotation_id' => $quotation->id,
            'payment_condition' => PaymentCondition::Cash->value,
            'items' => [
                [
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $this->cashMethod->id,
                    'amount' => 200.00,
                    'received_amount' => 200.00,
                ],
            ],
        ], $this->admin, $this->branch);

        $this->assertSame(QuotationStatus::Converted, $quotation->fresh()->status);
        $this->assertSame($sale->id, $quotation->fresh()->converted_sale_id);

        // Cancelar venta
        $this->authAs($this->admin)
            ->postJson("/api/v1/sales/sales/{$sale->id}/cancel", [
                'reason' => 'Anulación que revierte cotización',
            ])
            ->assertOk();

        $freshQuotation = $quotation->fresh();
        $this->assertSame(QuotationStatus::Accepted, $freshQuotation->status);
        $this->assertNull($freshQuotation->converted_sale_id);
    }

    public function test_cancellation_requires_permission(): void
    {
        $sale = $this->issueSampleSale();

        // Operator has no sales.cancel permission
        $response = $this->authAs($this->operator)
            ->postJson("/api/v1/sales/sales/{$sale->id}/cancel", [
                'reason' => 'Sin permisos',
            ]);

        $response->assertStatus(403);
    }
}
