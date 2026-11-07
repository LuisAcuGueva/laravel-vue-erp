<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Purchases;

use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Models\ProviderPrice;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseReceipt;
use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Models\SupplierPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SetsUpPayables;
use Tests\TestCase;

class PurchaseReceiptsApiTest extends TestCase
{
    use RefreshDatabase, SetsUpPayables;

    private const BASE = '/api/v1/purchases/receipts';

    private const ORDERS = '/api/v1/purchases/orders';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPayables();
    }

    protected function tearDown(): void
    {
        $this->tearDownPurchasing();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createApprovedOrder(array $overrides = [], ?Branch $branch = null): PurchaseOrder
    {
        $targetBranch = $branch ?? $this->branch;
        $this->actAs([
            'purchase_orders.view',
            'purchase_orders.create',
            'purchase_orders.update',
            'purchase_orders.approve',
        ], bothBranches: true);
        $this->withHeader('X-Branch-Id', $targetBranch->id);

        $payload = array_merge([
            'provider_id' => $this->provider->id,
            'warehouse_id' => $targetBranch->id === $this->otherBranch->id ? $this->otherWarehouse->id : $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(5)->toDateString(),
            'currency_id' => $this->pen->id,
            'payment_condition' => 'cash',
            'prices_include_tax' => false,
            'items' => [
                [
                    'item_presentation_id' => $this->productPresentation->id,
                    'quantity' => '10.0000',
                    'unit_price' => '50.0000',
                    'discount_percent' => '0.00',
                    'tax_type' => 'taxable',
                ],
                [
                    'item_presentation_id' => $this->servicePresentation->id,
                    'quantity' => '2.0000',
                    'unit_price' => '100.0000',
                    'discount_percent' => '0.00',
                    'tax_type' => 'taxable',
                ],
            ],
        ], $overrides);

        $res = $this->postJson(self::ORDERS, $payload)->assertCreated();
        $orderId = (string) $res->json('data.id');

        $this->postJson(self::ORDERS."/{$orderId}/submit")->assertOk();

        /** @var PurchaseOrder $order */
        $order = PurchaseOrder::with('items')->findOrFail($orderId);
        if ($order->status !== PurchaseOrderStatus::Approved) {
            $this->actAs(['purchase_orders.approve']);
            $this->postJson(self::ORDERS."/{$orderId}/approve")->assertOk();
            $order->refresh();
        }

        return $order;
    }

    public function test_can_get_pending_items_from_approved_order(): void
    {
        $order = $this->createApprovedOrder();

        $this->actAs(['purchase_receipts.create']);

        $res = $this->getJson(self::ORDERS."/{$order->id}/pending-items")->assertOk();

        /** @var list<array<string, mixed>> $items */
        $items = $res->json('data');
        $this->assertCount(2, $items);
        $this->assertEquals('10.0000', $items[0]['pending_quantity']);
        $this->assertEquals('2.0000', $items[1]['pending_quantity']);
        $this->assertEquals('50.0000', $items[0]['unit_cost_preview']);
    }

    public function test_can_create_draft_receipt_without_affecting_stock(): void
    {
        $order = $this->createApprovedOrder();
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->firstWhere('is_service', false);
        $this->assertNotNull($productItem);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.view']);

        $res = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'provider_document_number' => 'GR-001-000123',
            'notes' => 'Llegó en camión frigorífico',
            'items' => [
                [
                    'purchase_order_item_id' => $productItem->id,
                    'quantity' => '4.0000',
                    'notes' => 'Primer lote',
                ],
            ],
        ])->assertCreated();

        $receiptId = (string) $res->json('data.id');
        $this->assertStringStartsWith('RC-', (string) $res->json('data.number'));
        $this->assertEquals('draft', $res->json('data.status'));
        $this->assertEquals('200.00', $res->json('data.total_cost'));

        // Stock no debe haberse modificado en borrador
        $this->assertEquals(0, KardexMovement::where('source_id', $receiptId)->count());
        $freshProductItem = $productItem->fresh();
        $this->assertNotNull($freshProductItem);
        $this->assertEquals('0.0000', $freshProductItem->received_quantity);
    }

    public function test_invoice_document_type_requires_a_document_number(): void
    {
        $order = $this->createApprovedOrder();
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->firstWhere('is_service', false);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.view']);

        $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'items' => [
                ['purchase_order_item_id' => $productItem->id, 'quantity' => '4.0000'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['provider_document_number']);

        $this->assertSame(0, PurchaseReceipt::query()->count());
    }

    public function test_receive_all_with_invoice_document_type_requires_a_document_number(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm']);

        $this->postJson(self::ORDERS."/{$order->id}/receive-all", [
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
        ])->assertStatus(422)->assertJsonValidationErrors(['provider_document_number']);

        $this->assertSame(0, PurchaseReceipt::query()->count());
    }

    public function test_partial_receipt_confirmation_enters_stock_at_base_currency_cost_without_tax_and_updates_order(): void
    {
        // Orden en USD con precios que incluyen IGV (18%)
        // Precio unitario: 118 USD -> Sin IGV: 100 USD -> En PEN a TC 3.80: 380 PEN
        $order = $this->createApprovedOrder([
            'currency_id' => $this->usd->id,
            'exchange_rate' => '3.8000',
            'prices_include_tax' => true,
            'tax_rate' => '18.00',
            'items' => [
                [
                    'item_presentation_id' => $this->productPresentation->id,
                    'quantity' => '10.0000',
                    'unit_price' => '118.0000',
                    'discount_percent' => '0.00',
                    'tax_type' => 'taxable',
                ],
            ],
        ]);
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->first();
        $this->assertNotNull($productItem);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view']);

        // Registrar y confirmar recepción de 4 unidades
        $createRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'provider_document_number' => 'GR-001-4455',
            'items' => [
                [
                    'purchase_order_item_id' => $productItem->id,
                    'quantity' => '4.0000',
                ],
            ],
        ])->assertCreated();

        $receiptId = (string) $createRes->json('data.id');

        $confirmRes = $this->postJson(self::BASE."/{$receiptId}/confirm")->assertOk();
        $this->assertEquals('confirmed', $confirmRes->json('data.status'));

        // 1. Stock aumentado en almacén al costo unitario en moneda base sin impuesto (380.0000)
        $stock = Stock::where('warehouse_id', $this->warehouse->id)
            ->where('catalog_item_id', $this->product->id)
            ->first();
        $this->assertNotNull($stock);
        $this->assertEquals('4.0000', $stock->quantity);
        $this->assertEquals('380.0000', $stock->avg_cost);

        // 2. Movimiento de Kardex creado
        $kardex = KardexMovement::where('source_type', 'purchase_receipt')
            ->where('source_id', $receiptId)
            ->first();
        $this->assertNotNull($kardex);
        $this->assertEquals('4.0000', $kardex->quantity);
        $this->assertEquals('380.0000', $kardex->unit_cost);

        // 3. Orden actualizada a partially_received con 4 recibidos y 6 pendientes
        $productItem->refresh();
        $this->assertEquals('4.0000', $productItem->received_quantity);
        $this->assertEquals('6.0000', $productItem->pending_quantity);
        $freshOrder = $order->fresh();
        $this->assertNotNull($freshOrder);
        $this->assertEquals(PurchaseOrderStatus::PartiallyReceived, $freshOrder->status);

        // 4. Último costo de compra del ítem actualizado
        $freshProduct = $this->product->fresh();
        $this->assertNotNull($freshProduct);
        $this->assertEquals('380.000000', $freshProduct->last_purchase_cost);

        // 5. Último precio registrado para el proveedor (100.00 USD neto)
        $providerPrice = ProviderPrice::where('provider_id', $this->provider->id)
            ->where('item_presentation_id', $this->productPresentation->id)
            ->where('currency_id', $this->usd->id)
            ->first();
        $this->assertNotNull($providerPrice);
        $this->assertEquals('100.0000', $providerPrice->last_purchase_price);
    }

    public function test_final_receipt_transitions_order_to_received(): void
    {
        $order = $this->createApprovedOrder([
            'items' => [
                [
                    'item_presentation_id' => $this->productPresentation->id,
                    'quantity' => '5.0000',
                    'unit_price' => '20.0000',
                    'discount_percent' => '0.00',
                    'tax_type' => 'taxable',
                ],
            ],
        ]);
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->first();
        $this->assertNotNull($productItem);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm']);

        // 1. Recepción parcial de 2 unidades
        $r1 = (string) $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [['purchase_order_item_id' => $productItem->id, 'quantity' => '2.0000']],
        ])->json('data.id');
        $this->postJson(self::BASE."/{$r1}/confirm")->assertOk();
        $this->assertEquals(PurchaseOrderStatus::PartiallyReceived, $order->fresh()?->status);

        // 2. Recepción final de las 3 unidades restantes
        $r2 = (string) $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [['purchase_order_item_id' => $productItem->id, 'quantity' => '3.0000']],
        ])->json('data.id');
        $this->postJson(self::BASE."/{$r2}/confirm")->assertOk();

        $freshOrder = $order->fresh();
        $this->assertNotNull($freshOrder);
        $this->assertEquals(PurchaseOrderStatus::Received, $freshOrder->status);
        $this->assertEquals('5.0000', $productItem->fresh()?->received_quantity);
    }

    public function test_cannot_exceed_pending_quantity_when_tolerance_is_zero(): void
    {
        $order = $this->createApprovedOrder();
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->firstWhere('is_service', false);
        $this->assertNotNull($productItem);

        $this->actAs(['purchase_receipts.create']);

        // Intentar recibir 11 cuando sólo hay 10 pendientes y tolerancia es 0%
        $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [
                ['purchase_order_item_id' => $productItem->id, 'quantity' => '11.0000'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    public function test_can_exceed_pending_quantity_within_configured_tolerance(): void
    {
        SystemParameter::where('group_key', 'purchases')
            ->where('item_key', 'over_receipt_tolerance_percent')
            ->update(['value' => 10.0]);
        Cache::forget(SystemParameterService::CACHE_KEY);

        $order = $this->createApprovedOrder();
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->firstWhere('is_service', false);
        $this->assertNotNull($productItem);

        $this->actAs(['purchase_receipts.create']);

        // 10 pendientes + 10% tolerancia = 11.0000 máximo permitido
        $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [
                ['purchase_order_item_id' => $productItem->id, 'quantity' => '11.0000'],
            ],
        ])->assertCreated();

        // 11.5 excede la tolerancia del 10%
        $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [
                ['purchase_order_item_id' => $productItem->id, 'quantity' => '11.5000'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    public function test_service_lines_do_not_create_kardex_movements_but_update_received_quantity(): void
    {
        $order = $this->createApprovedOrder();
        /** @var PurchaseOrderItem $serviceItem */
        $serviceItem = $order->items->firstWhere('is_service', true);
        $this->assertNotNull($serviceItem);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm']);

        $res = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'E001-9988',
            'items' => [
                ['purchase_order_item_id' => $serviceItem->id, 'quantity' => '2.0000'],
            ],
        ])->assertCreated();

        $receiptId = (string) $res->json('data.id');
        $this->postJson(self::BASE."/{$receiptId}/confirm")->assertOk();

        // No debe crear kardex para servicios
        $this->assertEquals(0, KardexMovement::where('source_id', $receiptId)->count());

        // Debe incrementar cantidad recibida
        $this->assertEquals('2.0000', $serviceItem->fresh()?->received_quantity);
    }

    public function test_receive_all_shortcut_creates_and_confirms_receipt_for_all_pending_lines(): void
    {
        $order = $this->createApprovedOrder();

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm']);

        $res = $this->postJson(self::ORDERS."/{$order->id}/receive-all", [
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'provider_document_number' => 'GR-ALL-001',
        ])->assertCreated();

        $this->assertEquals('confirmed', $res->json('data.status'));
        $this->assertEquals(PurchaseOrderStatus::Received, $order->fresh()?->status);

        // Stock de producto incrementado a 10
        $stock = Stock::where('warehouse_id', $this->warehouse->id)
            ->where('catalog_item_id', $this->product->id)
            ->first();
        $this->assertNotNull($stock);
        $this->assertEquals('10.0000', $stock->quantity);
    }

    public function test_can_cancel_draft_receipt_without_affecting_stock_or_order(): void
    {
        $order = $this->createApprovedOrder();
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->firstWhere('is_service', false);
        $this->assertNotNull($productItem);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.cancel']);

        $res = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [
                ['purchase_order_item_id' => $productItem->id, 'quantity' => '5.0000'],
            ],
        ])->assertCreated();

        $receiptId = (string) $res->json('data.id');

        $cancelRes = $this->postJson(self::BASE."/{$receiptId}/cancel", [
            'reason' => 'Error en guía de proveedor',
        ])->assertOk();

        $this->assertEquals('cancelled', $cancelRes->json('data.status'));
        $this->assertEquals('0.0000', $productItem->fresh()?->received_quantity);
        $this->assertEquals(0, KardexMovement::where('source_id', $receiptId)->count());
    }

    public function test_can_cancel_confirmed_receipt_reverting_stock_and_recalculating_order_status(): void
    {
        $order = $this->createApprovedOrder([
            'items' => [
                [
                    'item_presentation_id' => $this->productPresentation->id,
                    'quantity' => '8.0000',
                    'unit_price' => '40.0000',
                    'discount_percent' => '0.00',
                    'tax_type' => 'taxable',
                ],
            ],
        ]);
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->first();
        $this->assertNotNull($productItem);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.cancel']);

        $res = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [['purchase_order_item_id' => $productItem->id, 'quantity' => '8.0000']],
        ])->assertCreated();

        $receiptId = (string) $res->json('data.id');
        $this->postJson(self::BASE."/{$receiptId}/confirm")->assertOk();

        $this->assertEquals(PurchaseOrderStatus::Received, $order->fresh()?->status);
        $this->assertEquals('8.0000', Stock::where('warehouse_id', $this->warehouse->id)->where('catalog_item_id', $this->product->id)->value('quantity'));

        // Anular la recepción confirmada
        $cancelRes = $this->postJson(self::BASE."/{$receiptId}/cancel", [
            'reason' => 'Mercadería rechazada por control de calidad',
        ])->assertOk();

        $this->assertEquals('cancelled', $cancelRes->json('data.status'));

        // Stock revertido a 0 mediante movimiento opuesto (reversal_out)
        $this->assertEquals('0.0000', Stock::where('warehouse_id', $this->warehouse->id)->where('catalog_item_id', $this->product->id)->value('quantity'));

        // Cantidad recibida en la orden revertida a 0 y estado devuelto a approved
        $this->assertEquals('0.0000', $productItem->fresh()?->received_quantity);
        $this->assertEquals(PurchaseOrderStatus::Approved, $order->fresh()?->status);
    }

    public function test_cannot_cancel_confirmed_receipt_if_stock_would_become_negative(): void
    {
        $order = $this->createApprovedOrder([
            'items' => [
                [
                    'item_presentation_id' => $this->productPresentation->id,
                    'quantity' => '10.0000',
                    'unit_price' => '30.0000',
                    'discount_percent' => '0.00',
                    'tax_type' => 'taxable',
                ],
            ],
        ]);
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->first();
        $this->assertNotNull($productItem);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.cancel']);

        $receiptId = (string) $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [['purchase_order_item_id' => $productItem->id, 'quantity' => '10.0000']],
        ])->json('data.id');

        $this->postJson(self::BASE."/{$receiptId}/confirm")->assertOk();

        // Simular salida de 6 unidades del inventario (dejando sólo 4 disponibles)
        /** @var StockLedgerService $ledger */
        $ledger = app(StockLedgerService::class);
        /** @var PurchaseReceipt $receipt */
        $receipt = PurchaseReceipt::query()->findOrFail($receiptId);

        DB::transaction(function () use ($ledger, $receipt) {
            $ledger->out(new StockMovementData(
                warehouseId: $this->warehouse->id,
                catalogItemId: $this->product->id,
                baseQuantity: 6.0,
                type: MovementType::NoteOut,
                source: $receipt,
                sourceNumber: 'NOTE-TEST-001',
            ));
        });

        // Intentar anular la recepción de 10 unidades cuando sólo hay 4 en stock disponible
        $this->postJson(self::BASE."/{$receiptId}/cancel", [
            'reason' => 'Anulación imposible por stock insuficiente',
        ])->assertStatus(422);

        $freshReceipt = $receipt->fresh();
        $this->assertNotNull($freshReceipt);
        $this->assertEquals('confirmed', $freshReceipt->status->value);
    }

    public function test_cannot_receive_order_from_another_branch(): void
    {
        // 1. Crear orden en otherBranch (SEDE-B)
        $order = $this->createApprovedOrder([], branch: $this->otherBranch);
        /** @var PurchaseOrderItem $firstItem */
        $firstItem = $order->items->first();
        $this->assertNotNull($firstItem);

        // 2. Cambiar a usuario con acceso sólo a branch (SEDE-A)
        $this->actAs(['purchase_receipts.create', 'purchase_receipts.view'], bothBranches: false);
        $this->withHeader('X-Branch-Id', $this->branch->id);

        $this->getJson(self::ORDERS."/{$order->id}/pending-items")->assertStatus(404);

        $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [['purchase_order_item_id' => $firstItem->id, 'quantity' => '1.0000']],
        ])->assertStatus(422);
    }

    public function test_authorization_permissions_return_403(): void
    {
        $order = $this->createApprovedOrder();
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->first();
        $this->assertNotNull($productItem);

        // 1. Sin permiso purchase_receipts.create
        $this->actAs(['purchase_receipts.view']);
        $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [['purchase_order_item_id' => $productItem->id, 'quantity' => '1.0000']],
        ])->assertForbidden();

        // Crear una recepción para probar confirm y cancel
        $this->actAs(['purchase_receipts.create']);
        $receiptId = (string) $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [['purchase_order_item_id' => $productItem->id, 'quantity' => '1.0000']],
        ])->json('data.id');

        // 2. Sin permiso purchase_receipts.confirm
        $this->actAs(['purchase_receipts.view']);
        $this->postJson(self::BASE."/{$receiptId}/confirm")->assertForbidden();

        // 3. Sin permiso purchase_receipts.cancel
        $this->actAs(['purchase_receipts.view']);
        $this->postJson(self::BASE."/{$receiptId}/cancel", ['reason' => 'Sin permiso'])->assertForbidden();
    }

    public function test_inventory_verify_stock_command_passes_after_receipt_and_cancel(): void
    {
        $order = $this->createApprovedOrder();
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->first();
        $this->assertNotNull($productItem);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.cancel']);

        // 1. Recibir y confirmar
        $receiptId = (string) $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'items' => [['purchase_order_item_id' => $productItem->id, 'quantity' => '5.0000']],
        ])->json('data.id');
        $this->postJson(self::BASE."/{$receiptId}/confirm")->assertOk();

        // 2. Verificar inventario en estado consistente
        $exitCode1 = Artisan::call('inventory:verify-stock');
        $this->assertSame(0, $exitCode1);

        // 3. Anular y re-verificar
        $this->postJson(self::BASE."/{$receiptId}/cancel", ['reason' => 'Prueba de consistencia'])->assertOk();
        $exitCode2 = Artisan::call('inventory:verify-stock');
        $this->assertSame(0, $exitCode2);
    }

    public function test_confirming_receipt_with_invoice_document_creates_supplier_invoice_automatically(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view']);

        $createRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-500',
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
        ])->assertCreated();

        $receiptId = (string) $createRes->json('data.id');

        $confirmRes = $this->postJson(self::BASE."/{$receiptId}/confirm")->assertOk();
        $this->assertCount(1, $confirmRes->json('data.supplier_invoices'));
        $this->assertSame('F001-500', $confirmRes->json('data.supplier_invoices.0.document_number'));

        $this->assertSame(1, SupplierInvoice::query()->count());

        /** @var SupplierInvoice $invoice */
        $invoice = SupplierInvoice::query()->firstOrFail();
        $this->assertSame($this->provider->id, $invoice->provider_id);
        $this->assertSame('F001-500', $invoice->document_number);
        $this->assertSame('pending', $invoice->status->value);
        $this->assertEquals('826.00', $invoice->total);
        $this->assertEquals('826.00', $invoice->balance);
        $this->assertSame(1, $invoice->receipts()->count());
        $this->assertTrue($invoice->receipts->contains('id', $receiptId));
        $this->assertSame(1, $invoice->installments()->count());
    }

    public function test_second_receipt_with_same_provider_invoice_number_merges_into_existing_invoice(): void
    {
        $order = $this->createApprovedOrder();
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->firstWhere('is_service', false);
        /** @var PurchaseOrderItem $serviceItem */
        $serviceItem = $order->items->firstWhere('is_service', true);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view']);

        // Primera recepción parcial (6 de 10 unidades) con la factura del proveedor F001-900.
        $firstRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-900',
            'items' => [
                ['purchase_order_item_id' => $productItem->id, 'quantity' => '6.0000'],
            ],
        ])->assertCreated();
        $this->postJson(self::BASE."/{$firstRes->json('data.id')}/confirm")->assertOk();

        $this->assertSame(1, SupplierInvoice::query()->count());

        // Segunda recepción con el resto (4 unidades del producto + el servicio) y el mismo documento.
        $secondRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-900',
            'items' => [
                ['purchase_order_item_id' => $productItem->id, 'quantity' => '4.0000'],
                ['purchase_order_item_id' => $serviceItem->id, 'quantity' => '2.0000'],
            ],
        ])->assertCreated();
        $this->postJson(self::BASE."/{$secondRes->json('data.id')}/confirm")->assertOk();

        // Debe seguir habiendo una sola factura, con ambas recepciones y el total combinado de la OC.
        $this->assertSame(1, SupplierInvoice::query()->count());

        /** @var SupplierInvoice $invoice */
        $invoice = SupplierInvoice::query()->firstOrFail();
        $this->assertEquals('826.00', $invoice->total);
        $this->assertEquals('826.00', $invoice->balance);
        $this->assertSame(2, $invoice->receipts()->count());
        $this->assertSame(3, $invoice->items()->count());
        $this->assertSame(1, $invoice->installments()->count());

        $installment = $invoice->installments()->firstOrFail();
        $this->assertEquals('826.00', $installment->amount);
        $this->assertEquals('826.00', $installment->balance);
    }

    public function test_receipt_with_guide_document_does_not_create_supplier_invoice(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view']);

        $createRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'provider_document_number' => 'GR-001-700',
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
        ])->assertCreated();

        $this->postJson(self::BASE."/{$createRes->json('data.id')}/confirm")->assertOk();

        $this->assertSame(0, SupplierInvoice::query()->count());
    }

    public function test_confirming_receipt_with_custom_installments_creates_invoice_with_that_schedule(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view']);

        $createRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-600',
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
        ])->assertCreated();

        $receiptId = (string) $createRes->json('data.id');

        $confirmRes = $this->postJson(self::BASE."/{$receiptId}/confirm", [
            'installments' => [
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => '275.33'],
                ['due_date' => now()->addDays(60)->toDateString(), 'amount' => '275.33'],
                ['due_date' => now()->addDays(90)->toDateString(), 'amount' => '275.34'],
            ],
        ])->assertOk();

        $this->assertSame([], $confirmRes->json('meta.warnings'));
        $this->assertCount(1, $confirmRes->json('data.supplier_invoices'));

        /** @var SupplierInvoice $invoice */
        $invoice = SupplierInvoice::query()->firstOrFail();
        $this->assertEquals('826.00', $invoice->total);
        $this->assertSame(3, $invoice->installments()->count());

        $amounts = $invoice->installments()->orderBy('number')->pluck('amount')->map(fn ($a) => (string) $a)->all();
        $this->assertSame(['275.33', '275.33', '275.34'], $amounts);
    }

    public function test_confirming_receipt_with_installments_not_matching_total_fails_and_blocks_confirmation(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view']);

        $createRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-601',
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
        ])->assertCreated();

        $receiptId = (string) $createRes->json('data.id');

        $this->postJson(self::BASE."/{$receiptId}/confirm", [
            'installments' => [
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => '100.00'],
                ['due_date' => now()->addDays(60)->toDateString(), 'amount' => '100.00'],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, SupplierInvoice::query()->count());
        $this->assertSame('draft', PurchaseReceipt::query()->findOrFail($receiptId)->status->value);
    }

    public function test_confirming_receipt_with_installment_due_date_before_issue_date_fails(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view']);

        $createRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-602',
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
        ])->assertCreated();

        $receiptId = (string) $createRes->json('data.id');

        $this->postJson(self::BASE."/{$receiptId}/confirm", [
            'installments' => [
                ['due_date' => now()->subDay()->toDateString(), 'amount' => '826.00'],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, SupplierInvoice::query()->count());
        $this->assertSame('draft', PurchaseReceipt::query()->findOrFail($receiptId)->status->value);
    }

    public function test_second_receipt_with_custom_installments_on_merge_is_ignored_with_warning(): void
    {
        $order = $this->createApprovedOrder();
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->firstWhere('is_service', false);
        /** @var PurchaseOrderItem $serviceItem */
        $serviceItem = $order->items->firstWhere('is_service', true);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view']);

        $firstRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-910',
            'items' => [
                ['purchase_order_item_id' => $productItem->id, 'quantity' => '6.0000'],
            ],
        ])->assertCreated();
        $this->postJson(self::BASE."/{$firstRes->json('data.id')}/confirm")->assertOk();

        /** @var SupplierInvoice $invoice */
        $invoice = SupplierInvoice::query()->firstOrFail();

        $secondRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-910',
            'items' => [
                ['purchase_order_item_id' => $productItem->id, 'quantity' => '4.0000'],
                ['purchase_order_item_id' => $serviceItem->id, 'quantity' => '2.0000'],
            ],
        ])->assertCreated();

        $confirmRes = $this->postJson(self::BASE."/{$secondRes->json('data.id')}/confirm", [
            'installments' => [
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => '100.00'],
            ],
        ])->assertOk();

        $this->assertSame(
            ["La factura {$invoice->number} ya existe; el cronograma indicado no se aplicó, la recepción se agregó a su cuota vigente."],
            $confirmRes->json('meta.warnings'),
        );

        $this->assertSame(1, SupplierInvoice::query()->count());
        $this->assertSame(1, $invoice->installments()->count());

        $installment = $invoice->installments()->firstOrFail();
        $this->assertEquals('826.00', $installment->amount);
    }

    public function test_receipt_with_guide_document_and_installments_payload_is_ignored_without_error(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view']);

        $createRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'guide',
            'provider_document_number' => 'GR-001-710',
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
        ])->assertCreated();

        $this->postJson(self::BASE."/{$createRes->json('data.id')}/confirm", [
            'installments' => [
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => '826.00'],
            ],
        ])->assertOk();

        $this->assertSame(0, SupplierInvoice::query()->count());
    }

    public function test_receive_all_with_custom_installments_creates_invoice_with_that_schedule(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm']);

        $res = $this->postJson(self::ORDERS."/{$order->id}/receive-all", [
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-999',
            'installments' => [
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => '275.33'],
                ['due_date' => now()->addDays(60)->toDateString(), 'amount' => '275.33'],
                ['due_date' => now()->addDays(90)->toDateString(), 'amount' => '275.34'],
            ],
        ])->assertCreated();

        $this->assertSame([], $res->json('meta.warnings'));

        /** @var SupplierInvoice $invoice */
        $invoice = SupplierInvoice::query()->firstOrFail();
        $this->assertEquals('826.00', $invoice->total);
        $this->assertSame(3, $invoice->installments()->count());
    }

    public function test_confirming_receipt_with_down_payment_pays_part_and_finances_remainder(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAsCashier(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view', 'supplier_payments.create']);

        $createRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-700',
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
        ])->assertCreated();

        $receiptId = (string) $createRes->json('data.id');

        $confirmRes = $this->postJson(self::BASE."/{$receiptId}/confirm", [
            'down_payment' => [
                'amount' => '200.00',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->session->id,
            ],
        ])->assertOk();

        $this->assertSame([], $confirmRes->json('meta.warnings'));

        /** @var SupplierInvoice $invoice */
        $invoice = SupplierInvoice::query()->firstOrFail();
        $this->assertEquals('826.00', $invoice->total);
        $this->assertEquals('200.00', $invoice->paid_amount);
        $this->assertEquals('626.00', $invoice->balance);
        $this->assertSame('partially_paid', $invoice->status->value);
        $this->assertSame(2, $invoice->installments()->count());

        $downPaymentInstallment = $invoice->installments()->where('number', 1)->firstOrFail();
        $this->assertEquals('200.00', $downPaymentInstallment->amount);
        $this->assertEquals('200.00', $downPaymentInstallment->paid_amount);
        $this->assertEquals('0.00', $downPaymentInstallment->balance);
        $this->assertSame('paid', $downPaymentInstallment->status->value);
        $this->assertSame(now()->toDateString(), $downPaymentInstallment->due_date->toDateString());

        $remainderInstallment = $invoice->installments()->where('number', 2)->firstOrFail();
        $this->assertEquals('626.00', $remainderInstallment->amount);
        $this->assertEquals('0.00', $remainderInstallment->paid_amount);
        $this->assertSame('pending', $remainderInstallment->status->value);

        /** @var SupplierPayment $payment */
        $payment = SupplierPayment::query()->firstOrFail();
        $this->assertEquals('200.00', $payment->amount);
        $this->assertSame($this->session->id, $payment->cash_session_id);
    }

    public function test_confirming_receipt_with_down_payment_and_custom_remainder_installments(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAsCashier(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view', 'supplier_payments.create']);

        $createRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-701',
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
        ])->assertCreated();

        $receiptId = (string) $createRes->json('data.id');

        // Total 826.00, al contado 200.00, saldo 626.00 repartido en 2 cuotas iguales.
        $this->postJson(self::BASE."/{$receiptId}/confirm", [
            'down_payment' => [
                'amount' => '200.00',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->session->id,
            ],
            'installments' => [
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => '313.00'],
                ['due_date' => now()->addDays(60)->toDateString(), 'amount' => '313.00'],
            ],
        ])->assertOk();

        /** @var SupplierInvoice $invoice */
        $invoice = SupplierInvoice::query()->firstOrFail();
        $this->assertSame(3, $invoice->installments()->count());

        $amounts = $invoice->installments()->orderBy('number')->pluck('amount')->map(fn ($a) => (string) $a)->all();
        $this->assertSame(['200.00', '313.00', '313.00'], $amounts);

        $statuses = $invoice->installments()->orderBy('number')->pluck('status')->map(fn ($s) => $s->value)->all();
        $this->assertSame(['paid', 'pending', 'pending'], $statuses);
    }

    public function test_confirming_receipt_with_down_payment_exceeding_total_fails_and_blocks_confirmation(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view', 'supplier_payments.create']);

        $createRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-702',
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
        ])->assertCreated();

        $receiptId = (string) $createRes->json('data.id');

        $this->postJson(self::BASE."/{$receiptId}/confirm", [
            'down_payment' => [
                'amount' => '900.00',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->session->id,
            ],
        ])->assertStatus(422);

        $this->assertSame(0, SupplierInvoice::query()->count());
        $this->assertSame('draft', PurchaseReceipt::query()->findOrFail($receiptId)->status->value);
    }

    public function test_confirming_receipt_with_down_payment_requires_payment_permission(): void
    {
        $order = $this->createApprovedOrder();
        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view']);

        $createRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-703',
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'purchase_order_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
        ])->assertCreated();

        $receiptId = (string) $createRes->json('data.id');

        $this->postJson(self::BASE."/{$receiptId}/confirm", [
            'down_payment' => [
                'amount' => '200.00',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->session->id,
            ],
        ])->assertForbidden();

        $this->assertSame('draft', PurchaseReceipt::query()->findOrFail($receiptId)->status->value);
        $this->assertSame(0, SupplierInvoice::query()->count());
    }

    public function test_second_receipt_with_down_payment_on_merge_is_ignored_with_warning(): void
    {
        $order = $this->createApprovedOrder();
        /** @var PurchaseOrderItem $productItem */
        $productItem = $order->items->firstWhere('is_service', false);
        /** @var PurchaseOrderItem $serviceItem */
        $serviceItem = $order->items->firstWhere('is_service', true);

        $this->actAs(['purchase_receipts.create', 'purchase_receipts.confirm', 'purchase_receipts.view', 'supplier_payments.create']);

        $firstRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-920',
            'items' => [
                ['purchase_order_item_id' => $productItem->id, 'quantity' => '6.0000'],
            ],
        ])->assertCreated();
        $this->postJson(self::BASE."/{$firstRes->json('data.id')}/confirm")->assertOk();

        /** @var SupplierInvoice $invoice */
        $invoice = SupplierInvoice::query()->firstOrFail();

        $secondRes = $this->postJson(self::BASE, [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => 'invoice',
            'provider_document_number' => 'F001-920',
            'items' => [
                ['purchase_order_item_id' => $productItem->id, 'quantity' => '4.0000'],
                ['purchase_order_item_id' => $serviceItem->id, 'quantity' => '2.0000'],
            ],
        ])->assertCreated();

        $confirmRes = $this->postJson(self::BASE."/{$secondRes->json('data.id')}/confirm", [
            'down_payment' => [
                'amount' => '100.00',
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->session->id,
            ],
        ])->assertOk();

        $this->assertSame(
            ["La factura {$invoice->number} ya existe; el pago al contado indicado no se aplicó, la recepción se agregó a su cuota vigente."],
            $confirmRes->json('meta.warnings'),
        );

        $this->assertSame(1, SupplierInvoice::query()->count());
        $this->assertSame(0, SupplierPayment::query()->count());
        $this->assertSame(1, $invoice->installments()->count());
    }
}
