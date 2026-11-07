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
use App\Modules\Sales\Enums\CreditNoteReason;
use App\Modules\Sales\Enums\InstallmentStatus;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Services\SaleIssuanceService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
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

class CreditNoteSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Branch $branch;

    private Warehouse $warehouse;

    private Box $box;

    private CashSession $cashSession;

    private DocumentSeries $seriesFactura;

    private DocumentSeries $seriesNcFactura;

    private Client $client;

    private Employee $seller;

    private CatalogItem $product;

    private ItemPresentation $presentation;

    private PaymentMethod $cashMethod;

    private PaymentMethod $bankMethod;

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
            'code' => 'CAJ-01',
            'name' => 'Caja Principal Ventas',
            'is_active' => true,
        ]);
        $this->box->roles()->sync([$adminRole->id]);

        $this->cashSession = CashSession::create([
            'number' => 'SES-000001',
            'branch_id' => $this->branch->id,
            'box_id' => $this->box->id,
            'opened_by' => $this->admin->id,
            'status' => CashSessionStatus::Open->value,
            'opening_amount' => '500.00',
            'opened_at' => now(),
        ]);

        $this->seriesFactura = DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::Invoice,
            'series' => 'F001',
            'last_number' => 20,
            'is_electronic' => true,
            'is_active' => true,
        ]);

        $this->seriesNcFactura = DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::CreditNote,
            'series' => 'FC01',
            'last_number' => 0,
            'is_electronic' => true,
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'client_type' => 'company',
            'document_type' => 'RUC',
            'document_number' => '20608888881',
            'name' => 'CORPORACION METALMECANICA S.A.C.',
            'trade_name' => 'CORP METAL',
            'address' => 'Av. Argentina 1000',
            'default_price_type' => PriceType::Retail->value,
            'is_active' => true,
        ]);
        $this->client->forceFill(['credit_limit' => '20000.00'])->save();

        $this->seller = Employee::create([
            'branch_id' => $this->branch->id,
            'document_type' => 'DNI',
            'document_number' => '44558899',
            'first_name' => 'Mario',
            'last_name' => 'Vendedor',
            'is_seller' => true,
            'is_active' => true,
        ]);

        $unit = Unit::first() ?? Unit::create(['name' => 'Unidad', 'symbol' => 'NIU']);
        $category = Category::first() ?? Category::create(['name' => 'General', 'code' => 'GEN']);

        $this->product = CatalogItem::create([
            'code' => 'PROD-STL-01',
            'name' => 'Producto Liquidable',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'track_stock' => true,
            'tax_type' => TaxType::Taxable,
            'is_active' => true,
        ]);

        $this->presentation = ItemPresentation::create([
            'catalog_item_id' => $this->product->id,
            'unit_id' => $unit->id,
            'name' => 'Unidad',
            'factor' => 1,
            'is_base' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        ItemPrice::create([
            'item_presentation_id' => $this->presentation->id,
            'price_type' => PriceType::Retail,
            'currency_id' => $this->pen->id,
            'price' => '100.00',
        ]);

        $this->cashMethod = PaymentMethod::where('settlement', 'cash_box')->firstOrFail();
        $this->bankMethod = PaymentMethod::where('settlement', 'bank_account')->firstOrFail();

        if ($this->bankMethod->bank_account_id) {
            BankPeriod::firstOrCreate(
                [
                    'bank_account_id' => $this->bankMethod->bank_account_id,
                    'status' => BankPeriodStatus::Open,
                ],
                [
                    'number' => 1,
                    'opened_at' => now()->startOfMonth()->toDateString(),
                    'opening_balance' => '10000.00',
                ]
            );
        }

        // Stock inicial de 50 unidades al costo de 40.00
        /** @var StockLedgerService $stockLedger */
        $stockLedger = app(StockLedgerService::class);
        $stockLedger->in(new StockMovementData(
            warehouseId: $this->warehouse->id,
            catalogItemId: $this->product->id,
            baseQuantity: 50.0,
            type: MovementType::Opening,
            source: $this->warehouse,
            sourceNumber: 'INI-02',
            unitCost: 40.00,
            presentationId: $this->presentation->id,
            presentationQuantity: 50.0,
        ));
        app(ActiveBranch::class)->set($this->branch);
    }

    private function authHeaders(): array
    {
        /** @var JwtService $jwt */
        $jwt = app(JwtService::class);
        $tokens = $jwt->createTokenPair($this->admin);

        return [
            'Authorization' => "Bearer {$tokens['access_token']}",
            'X-Branch-Id' => $this->branch->id,
            'Accept' => 'application/json',
        ];
    }

    public function test_credit_sale_auto_mode_reduces_installments_from_the_last(): void
    {
        /** @var SaleIssuanceService $saleIssuance */
        $saleIssuance = app(SaleIssuanceService::class);

        // Venta al crédito de 5 unidades = S/ 500.00 en 2 cuotas: #1: 250, #2: 250
        $sale = $saleIssuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'client_id' => $this->client->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'credit_days' => 60,
            'installments_count' => 2,
            'installment_interval_days' => 30,
            'seller_id' => $this->seller->id,
            'items' => [
                [
                    'catalog_item_id' => $this->product->id,
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 5,
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                    'tax_type' => 'taxable',
                ],
            ],
            'payments' => [],
        ], $this->admin, $this->branch);

        $this->assertEquals('500.00', (string) $sale->balance);
        $installments = $sale->installments()->orderBy('number')->get();
        $this->assertCount(2, $installments);
        $this->assertEquals('250.00', (string) $installments[0]->balance);
        $this->assertEquals('250.00', (string) $installments[1]->balance);

        $headers = $this->authHeaders();

        // Emitir nota de crédito por devolución parcial de 3 unidades = S/ 300.00
        // Como mode = 'auto':
        // toBalance = min(300, 500) = 300.00
        // Se reduce cuota #2 (250) por completo, y cuota #1 por 50.00
        $res = $this->postJson('/api/v1/sales/credit-notes', [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_ITEM->value,
            'reason_description' => 'Devolución de 3 unidades al crédito',
            'items' => [
                [
                    'sale_item_id' => $sale->items->firstOrFail()->id,
                    'quantity' => 3,
                    'return_to_stock' => true,
                ],
            ],
            'settlement' => [
                'mode' => 'auto',
            ],
        ], $headers);

        $res->assertStatus(201)
            ->assertJsonPath('data.total', '300.00')
            ->assertJsonPath('data.applied_to_balance', '300.00')
            ->assertJsonPath('data.refunded_amount', '0.00');

        $sale->refresh();
        $this->assertEquals('200.00', (string) $sale->balance);

        $instUpdated = $sale->installments()->orderBy('number')->get();
        // Cuota #2 cancelada por completo (amortizada desde la última)
        $this->assertEquals('0.00', (string) $instUpdated[1]->balance);
        $this->assertEquals(InstallmentStatus::Paid, $instUpdated[1]->status);

        // Cuota #1 amortizada por 50.00 -> saldo restante 200.00
        $this->assertEquals('200.00', (string) $instUpdated[0]->balance);
        $this->assertEquals(InstallmentStatus::PartiallyPaid, $instUpdated[0]->status);
    }

    public function test_credit_sale_auto_mode_refunds_excess_over_balance(): void
    {
        /** @var SaleIssuanceService $saleIssuance */
        $saleIssuance = app(SaleIssuanceService::class);

        // Venta de 4 unidades = S/ 400.00 con adelanto de S/ 300.00 en efectivo -> saldo crédito S/ 100.00
        $sale = $saleIssuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'client_id' => $this->client->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'credit_days' => 30,
            'cash_session_id' => $this->cashSession->id,
            'seller_id' => $this->seller->id,
            'items' => [
                [
                    'catalog_item_id' => $this->product->id,
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 4,
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                    'tax_type' => 'taxable',
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $this->cashMethod->id,
                    'cash_session_id' => $this->cashSession->id,
                    'amount' => 300.00,
                    'reference' => 'Adelanto contado',
                ],
            ],
        ], $this->admin, $this->branch);

        $this->assertEquals('100.00', (string) $sale->balance);

        $headers = $this->authHeaders();

        // Emitir nota de crédito por 2 unidades = S/ 200.00
        // toBalance = 100.00 (salda la deuda)
        // refund = 100.00 (reembolso en efectivo)
        $res = $this->postJson('/api/v1/sales/credit-notes', [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_ITEM->value,
            'reason_description' => 'Devolución de 2 unidades',
            'items' => [
                [
                    'sale_item_id' => $sale->items->firstOrFail()->id,
                    'quantity' => 2,
                    'return_to_stock' => true,
                ],
            ],
            'settlement' => [
                'mode' => 'auto',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ], $headers);

        $res->assertStatus(201)
            ->assertJsonPath('data.total', '200.00')
            ->assertJsonPath('data.applied_to_balance', '100.00')
            ->assertJsonPath('data.refunded_amount', '100.00');

        $sale->refresh();
        $this->assertEquals('0.00', (string) $sale->balance);
    }

    public function test_cash_insufficient_throws_422(): void
    {
        /** @var SaleIssuanceService $saleIssuance */
        $saleIssuance = app(SaleIssuanceService::class);

        $sale = $saleIssuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'client_id' => $this->client->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Cash->value,
            'seller_id' => $this->seller->id,
            'items' => [
                [
                    'catalog_item_id' => $this->product->id,
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 10,
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                    'tax_type' => 'taxable',
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $this->bankMethod->id,
                    'amount' => 1000.00,
                    'reference' => 'Transferencia bancaria',
                ],
            ],
        ], $this->admin, $this->branch);

        // La caja solo tiene S/ 500.00 de apertura. Intentar reembolsar S/ 800.00 en caja debe fallar
        $headers = $this->authHeaders();

        $this->postJson('/api/v1/sales/credit-notes', [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_ITEM->value,
            'reason_description' => 'Devolución de 8 unidades',
            'items' => [
                [
                    'sale_item_id' => $sale->items->firstOrFail()->id,
                    'quantity' => 8,
                    'return_to_stock' => true,
                ],
            ],
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settlement.cash_session_id']);
    }

    public function test_cancel_credit_note_restores_exact_installments_and_statement(): void
    {
        /** @var SaleIssuanceService $saleIssuance */
        $saleIssuance = app(SaleIssuanceService::class);

        $sale = $saleIssuance->issue([
            'document_series_id' => $this->seriesFactura->id,
            'client_id' => $this->client->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->pen->id,
            'payment_condition' => PaymentCondition::Credit->value,
            'credit_days' => 30,
            'cash_session_id' => $this->cashSession->id,
            'seller_id' => $this->seller->id,
            'items' => [
                [
                    'catalog_item_id' => $this->product->id,
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => 3,
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                    'tax_type' => 'taxable',
                ],
            ],
            'payments' => [],
        ], $this->admin, $this->branch);

        $this->assertEquals('300.00', (string) $sale->balance);

        $headers = $this->authHeaders();

        // Emitir nota de crédito por S/ 100.00 aplicada a la cuota
        $createRes = $this->postJson('/api/v1/sales/credit-notes', [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DESCUENTO_GLOBAL->value,
            'reason_description' => 'Descuento para probar reversión',
            'amount' => 100.00,
            'settlement' => ['mode' => 'auto'],
        ], $headers);

        $createRes->assertStatus(201);
        $creditNoteId = $createRes->json('data.id');

        $sale->refresh();
        $this->assertEquals('200.00', (string) $sale->balance);

        // Consultar estado de cuenta del cliente: debe incluir la nota como abono
        $stmtRes = $this->getJson("/api/v1/clients/{$this->client->id}/statement?from=".now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString(), $headers);
        $stmtRes->assertStatus(200);

        $movements = $stmtRes->json('movements') ?? $stmtRes->json('data.movements') ?? [];
        $hasCreditNoteMovement = collect($movements)->contains(fn ($m) => $m['type'] === 'credit_note' && $m['credit'] === '100.00');
        $this->assertTrue($hasCreditNoteMovement);

        // Anular la nota de crédito
        $this->postJson("/api/v1/sales/credit-notes/{$creditNoteId}/cancel", [
            'reason' => 'Anulación de prueba de reversión',
        ], $headers)->assertStatus(200);

        $sale->refresh();
        $this->assertEquals('300.00', (string) $sale->balance);

        $installment = $sale->installments->firstOrFail();
        $this->assertEquals('300.00', (string) $installment->balance);
        $this->assertEquals('0.00', (string) $installment->paid_amount);
    }
}
