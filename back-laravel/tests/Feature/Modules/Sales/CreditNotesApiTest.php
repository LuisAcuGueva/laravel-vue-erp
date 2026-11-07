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
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Roles\Models\Role;
use App\Modules\Sales\Enums\CreditNoteReason;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SaleCancellationService;
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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreditNotesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Branch $branch;

    private Warehouse $warehouse;

    private Box $box;

    private CashSession $cashSession;

    private DocumentSeries $seriesFactura;

    private DocumentSeries $seriesNcFactura;

    private DocumentSeries $seriesNcBoleta;

    private Client $client;

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
            'opening_amount' => '1000.00',
            'opened_at' => now(),
        ]);

        // Series de Factura y de Notas de Crédito
        $this->seriesFactura = DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::Invoice,
            'series' => 'F001',
            'last_number' => 10,
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

        $this->seriesNcBoleta = DocumentSeries::create([
            'branch_id' => $this->branch->id,
            'document_type' => SalesDocumentType::CreditNote,
            'series' => 'BC01',
            'last_number' => 0,
            'is_electronic' => true,
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'client_type' => 'company',
            'document_type' => 'RUC',
            'document_number' => '20609999991',
            'name' => 'DISTRIBUIDORA INDUSTRIAL S.A.C.',
            'trade_name' => 'DISTRIB IND',
            'address' => 'Av. Industrial 456',
            'default_price_type' => PriceType::Retail->value,
            'is_active' => true,
        ]);
        $this->client->forceFill(['credit_limit' => '10000.00'])->save();

        $this->seller = Employee::create([
            'branch_id' => $this->branch->id,
            'document_type' => 'DNI',
            'document_number' => '44556677',
            'first_name' => 'Pedro',
            'last_name' => 'Vendedor',
            'is_seller' => true,
            'is_active' => true,
        ]);

        $unit = Unit::first() ?? Unit::create(['name' => 'Unidad', 'symbol' => 'NIU']);
        $category = Category::first() ?? Category::create(['name' => 'General', 'code' => 'GEN']);

        $this->product = CatalogItem::create([
            'code' => 'PROD-NC-01',
            'name' => 'Repuesto Original X',
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

        // Stock inicial de 10 unidades al costo unitario de 40.00
        /** @var StockLedgerService $stockLedger */
        $stockLedger = app(StockLedgerService::class);
        $stockLedger->in(new StockMovementData(
            warehouseId: $this->warehouse->id,
            catalogItemId: $this->product->id,
            baseQuantity: 10.0,
            type: MovementType::Opening,
            source: $this->warehouse,
            sourceNumber: 'INI-01',
            unitCost: 40.00,
            presentationId: $this->presentation->id,
            presentationQuantity: 10.0,
        ));

        app(ActiveBranch::class)->set($this->branch);
    }

    private function authHeaders(User $user): array
    {
        /** @var JwtService $jwt */
        $jwt = app(JwtService::class);
        $tokens = $jwt->createTokenPair($user);

        return [
            'Authorization' => "Bearer {$tokens['access_token']}",
            'X-Branch-Id' => $this->branch->id,
            'Accept' => 'application/json',
        ];
    }

    private function getStock(): float
    {
        return (float) (Stock::where('warehouse_id', $this->warehouse->id)
            ->where('catalog_item_id', $this->product->id)
            ->value('quantity') ?? 0);
    }

    private function createSale(int $quantity = 5, string $condition = 'cash'): Sale
    {
        /** @var SaleIssuanceService $saleIssuance */
        $saleIssuance = app(SaleIssuanceService::class);

        $payload = [
            'document_series_id' => $this->seriesFactura->id,
            'client_id' => $this->client->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->pen->id,
            'cash_session_id' => $this->cashSession->id,
            'payment_condition' => $condition,
            'seller_id' => $this->seller->id,
            'notes' => 'Venta para prueba de notas de crédito',
            'items' => [
                [
                    'catalog_item_id' => $this->product->id,
                    'item_presentation_id' => $this->presentation->id,
                    'quantity' => $quantity,
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                    'tax_type' => 'taxable',
                ],
            ],
            'payments' => $condition === 'cash' ? [
                [
                    'payment_method_id' => $this->cashMethod->id,
                    'cash_session_id' => $this->cashSession->id,
                    'amount' => $quantity * 100.00,
                    'reference' => 'Efectivo',
                ],
            ] : [],
        ];

        return $saleIssuance->issue($payload, $this->admin, $this->branch);
    }

    public function test_forbidden_without_permissions(): void
    {
        $headers = $this->authHeaders($this->unauthorizedUser);

        $this->getJson('/api/v1/sales/credit-notes', $headers)
            ->assertStatus(403);

        $this->getJson('/api/v1/sales/credit-notes/summary', $headers)
            ->assertStatus(403);

        $this->postJson('/api/v1/sales/credit-notes', [], $headers)
            ->assertStatus(403);

        $this->postJson('/api/v1/sales/credit-notes/00000000-0000-0000-0000-000000000000/cancel', ['reason' => 'test error'], $headers)
            ->assertStatus(403);
    }

    public function test_summary_returns_counts_and_totals_by_currency_excluding_cancelled(): void
    {
        $sale = $this->createSale(quantity: 10);
        $saleItem = $sale->items->firstOrFail();
        $headers = $this->authHeaders($this->admin);

        $basePayload = [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_ITEM->value,
            'reason_description' => 'Devolución de unidades',
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ];

        $firstPayload = $basePayload;
        $firstPayload['items'] = [['sale_item_id' => $saleItem->id, 'quantity' => 2, 'return_to_stock' => true]];

        $firstId = $this->postJson('/api/v1/sales/credit-notes', $firstPayload, $headers)
            ->assertStatus(201)
            ->json('data.id');

        $secondPayload = $basePayload;
        $secondPayload['items'] = [['sale_item_id' => $saleItem->id, 'quantity' => 1, 'return_to_stock' => true]];

        $this->postJson('/api/v1/sales/credit-notes', $secondPayload, $headers)
            ->assertStatus(201);

        $this->postJson("/api/v1/sales/credit-notes/{$firstId}/cancel", [
            'reason' => 'Error en el sustento de la nota de crédito',
        ], $headers)->assertStatus(200);

        $response = $this->getJson('/api/v1/sales/credit-notes/summary', $headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_count', 2)
            ->assertJsonPath('data.issued_count', 1)
            ->assertJsonPath('data.cancelled_count', 1)
            ->assertJsonPath('data.totals_by_currency.0.currency_code', 'PEN')
            ->assertJsonPath('data.totals_by_currency.0.total', '100.00');
    }

    public function test_emit_partial_return_credit_note_and_returns_stock_at_original_cost(): void
    {
        $sale = $this->createSale(quantity: 5);
        $saleItem = $sale->items->firstOrFail();

        $stockAfterSale = $this->getStock();
        $this->assertEquals(5.0, $stockAfterSale); // 10 inicial - 5 vendido

        $headers = $this->authHeaders($this->admin);

        // Devolución parcial de 2 unidades
        $payload = [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_ITEM->value,
            'reason_description' => 'Devolución de 2 unidades por cambio de modelo',
            'items' => [
                [
                    'sale_item_id' => $saleItem->id,
                    'quantity' => 2,
                    'return_to_stock' => true,
                ],
            ],
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ];

        $response = $this->postJson('/api/v1/sales/credit-notes', $payload, $headers);
        $response->assertStatus(201)
            ->assertJsonPath('data.full_number', 'FC01-00000001')
            ->assertJsonPath('data.total', '200.00')
            ->assertJsonPath('data.refunded_amount', '200.00');

        $stockAfterNc = $this->getStock();
        $this->assertEquals(7.0, $stockAfterNc); // 5 + 2 reingresadas

        // Verificar movimiento en kardex: tipo sale_return al costo original 40.00
        /** @var CreditNote $note */
        $note = CreditNote::where('full_number', 'FC01-00000001')->firstOrFail();
        $km = $note->kardexMovements()->first();
        $this->assertNotNull($km);
        $this->assertEquals(MovementType::SaleReturn, $km->movement_type);
        $this->assertEquals(40.0, (float) $km->unit_cost);

        // Intentar devolver 4 unidades más sobre la misma línea debe fallar (solo quedan 3 pendientes)
        $payloadExcess = $payload;
        $payloadExcess['items'][0]['quantity'] = 4;

        $this->postJson('/api/v1/sales/credit-notes', $payloadExcess, $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // Devolver las 3 unidades restantes
        $payloadRemaining = $payload;
        $payloadRemaining['items'][0]['quantity'] = 3;

        $this->postJson('/api/v1/sales/credit-notes', $payloadRemaining, $headers)
            ->assertStatus(201)
            ->assertJsonPath('data.full_number', 'FC01-00000002');

        $stockFinal = $this->getStock();
        $this->assertEquals(10.0, $stockFinal); // Se recuperó el stock completo original
    }

    public function test_emit_full_return_credit_note(): void
    {
        $sale = $this->createSale(quantity: 3);

        $headers = $this->authHeaders($this->admin);

        $payload = [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_TOTAL->value,
            'reason_description' => 'Devolución total por cancelación del pedido',
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ];

        $response = $this->postJson('/api/v1/sales/credit-notes', $payload, $headers);
        $response->assertStatus(201)
            ->assertJsonPath('data.total', '300.00');

        $this->assertEquals(10.0, $this->getStock());
    }

    public function test_emit_item_return_credit_note_accepts_zero_amount_sent_by_frontend(): void
    {
        // El formulario del frontend siempre envía "amount" en cada ítem (0 por defecto)
        // aunque el motivo 07 (devolución por ítem) solo use "quantity".
        $sale = $this->createSale(quantity: 5);
        $saleItem = $sale->items->firstOrFail();

        $headers = $this->authHeaders($this->admin);

        $payload = [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_ITEM->value,
            'reason_description' => 'Devolución por producto defectuoso',
            'items' => [
                [
                    'sale_item_id' => $saleItem->id,
                    'quantity' => 2,
                    'amount' => 0,
                    'return_to_stock' => true,
                ],
            ],
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ];

        $this->postJson('/api/v1/sales/credit-notes', $payload, $headers)
            ->assertStatus(201);
    }

    public function test_emit_item_return_credit_note_rejects_zero_quantity(): void
    {
        $sale = $this->createSale(quantity: 5);
        $saleItem = $sale->items->firstOrFail();

        $headers = $this->authHeaders($this->admin);

        $payload = [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_ITEM->value,
            'reason_description' => 'Devolución por producto defectuoso',
            'items' => [
                [
                    'sale_item_id' => $saleItem->id,
                    'quantity' => 0,
                    'amount' => 0,
                    'return_to_stock' => true,
                ],
            ],
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ];

        $this->postJson('/api/v1/sales/credit-notes', $payload, $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_operation_cancellation_reason_01_fails_if_credit_notes_exist(): void
    {
        $sale = $this->createSale(quantity: 4);
        $saleItem = $sale->items->firstOrFail();

        $headers = $this->authHeaders($this->admin);

        // Emitir primera nota parcial (07)
        $this->postJson('/api/v1/sales/credit-notes', [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_ITEM->value,
            'reason_description' => 'Devolución de 1 unidad',
            'items' => [
                [
                    'sale_item_id' => $saleItem->id,
                    'quantity' => 1,
                    'return_to_stock' => true,
                ],
            ],
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ], $headers)->assertStatus(201);

        // Intentar anulación total (01) sobre la venta que ya tiene una nota emitida debe fallar
        $this->postJson('/api/v1/sales/credit-notes', [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::ANULACION_OPERACION->value,
            'reason_description' => 'Anulación total de venta',
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason_code']);
    }

    public function test_series_prefix_mismatch_throws_422(): void
    {
        $sale = $this->createSale(quantity: 2); // Es Factura (F001)

        $headers = $this->authHeaders($this->admin);

        // Intentar usar serie de boleta (BC01) para una factura debe responder 422
        $this->postJson('/api/v1/sales/credit-notes', [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcBoleta->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_TOTAL->value,
            'reason_description' => 'Devolución total',
            'settlement' => ['mode' => 'auto'],
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document_series_id']);
    }

    public function test_global_discount_reason_04_without_stock_movement(): void
    {
        $sale = $this->createSale(quantity: 5);

        $stockBefore = $this->getStock();

        $headers = $this->authHeaders($this->admin);

        $response = $this->postJson('/api/v1/sales/credit-notes', [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DESCUENTO_GLOBAL->value,
            'reason_description' => 'Descuento comercial posterior por volumen',
            'amount' => 50.00,
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ], $headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.total', '50.00');

        $stockAfter = $this->getStock();
        $this->assertEquals($stockBefore, $stockAfter); // No hubo movimiento físico de stock
    }

    public function test_sale_cannot_be_cancelled_if_active_credit_notes_exist(): void
    {
        $sale = $this->createSale(quantity: 2);

        $headers = $this->authHeaders($this->admin);

        $this->postJson('/api/v1/sales/credit-notes', [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DESCUENTO_GLOBAL->value,
            'reason_description' => 'Descuento posterior',
            'amount' => 20.00,
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ], $headers)->assertStatus(201);

        /** @var SaleCancellationService $cancellationService */
        $cancellationService = app(SaleCancellationService::class);

        $this->expectException(ValidationException::class);
        $cancellationService->cancel($sale, 'Intento de anulación', $this->admin);
    }

    public function test_cancel_credit_note_reverses_stock_and_settlement(): void
    {
        $sale = $this->createSale(quantity: 4);
        $saleItem = $sale->items->firstOrFail();

        $headers = $this->authHeaders($this->admin);

        $createRes = $this->postJson('/api/v1/sales/credit-notes', [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_ITEM->value,
            'reason_description' => 'Devolución de 2 unidades',
            'items' => [
                [
                    'sale_item_id' => $saleItem->id,
                    'quantity' => 2,
                    'return_to_stock' => true,
                ],
            ],
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ], $headers)->assertStatus(201);

        $creditNoteId = $createRes->json('data.id');

        $this->assertEquals(8.0, $this->getStock());

        // Anular la nota de crédito
        $cancelRes = $this->postJson("/api/v1/sales/credit-notes/{$creditNoteId}/cancel", [
            'reason' => 'Error en el sustento de la nota de crédito',
        ], $headers);

        $cancelRes->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // El stock reingresado debe haberse retirado (reversión)
        $this->assertEquals(6.0, $this->getStock());
    }

    public function test_credit_note_pdf_generation(): void
    {
        $sale = $this->createSale(quantity: 2);

        $headers = $this->authHeaders($this->admin);

        $createRes = $this->postJson('/api/v1/sales/credit-notes', [
            'sale_id' => $sale->id,
            'document_series_id' => $this->seriesNcFactura->id,
            'reason_code' => CreditNoteReason::DEVOLUCION_TOTAL->value,
            'reason_description' => 'Devolución total para PDF',
            'settlement' => [
                'mode' => 'refund',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->cashSession->id,
            ],
        ], $headers)->assertStatus(201);

        $creditNoteId = $createRes->json('data.id');

        // Formato A4
        $pdfA4 = $this->get("/api/v1/sales/credit-notes/{$creditNoteId}/pdf?format=a4", $headers);
        $pdfA4->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // Formato Ticket
        $pdfTicket = $this->get("/api/v1/sales/credit-notes/{$creditNoteId}/pdf?format=ticket", $headers);
        $pdfTicket->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
