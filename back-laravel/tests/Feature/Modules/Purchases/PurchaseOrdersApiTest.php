<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Purchases;

use App\Modules\Configuration\Models\CompanySetting;
use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Models\Currency;
use App\Modules\Inventory\Exceptions\InventoryRuleException;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Services\CatalogItemService;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Purchases\Models\PurchaseRequestItem;
use App\Modules\Purchases\Services\PurchaseOrderPdfBuilder;
use App\Modules\Purchases\Services\PurchaseOrderWorkflowService;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpPurchasing;
use Tests\TestCase;

class PurchaseOrdersApiTest extends TestCase
{
    use RefreshDatabase, SetsUpPurchasing;

    private const BASE = '/api/v1/purchases/orders';

    private const REQUESTS = '/api/v1/purchases/requests';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPurchasing();
    }

    protected function tearDown(): void
    {
        $this->tearDownPurchasing();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'provider_id' => $this->provider->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'currency_id' => $this->pen->id,
            'payment_condition' => 'cash',
            'prices_include_tax' => false,
            'notes' => 'Entregar en puerta 2',
            'terms' => 'Pago contra entrega',
            'items' => [
                ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '10', 'unit_price' => '100'],
            ],
        ], $overrides);
    }

    private function buyer(): void
    {
        $this->actAs(['purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_requests.view', 'purchase_requests.create']);
    }

    private function createOrder(array $overrides = []): string
    {
        return $this->postJson(self::BASE, $this->payload($overrides))->assertCreated()->json('data.id');
    }

    /**
     * Crea un requerimiento aprobado y devuelve el id de su primera línea (cantidad 10 del producto).
     */
    private function approvedRequestItem(string $quantity = '10'): string
    {
        $requester = $this->actAs(['purchase_requests.view', 'purchase_requests.create']);
        $id = $this->postJson(self::REQUESTS, [
            'area_id' => $this->area->id,
            'warehouse_id' => $this->warehouse->id,
            'required_date' => now()->addDays(3)->toDateString(),
            'priority' => 'normal',
            'items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => $quantity]],
        ])->assertCreated()->json('data.id');
        $this->postJson(self::REQUESTS."/{$id}/submit")->assertOk();

        $this->actAs(['purchase_requests.approve']);
        $this->postJson(self::REQUESTS."/{$id}/approve")->assertOk();
        $this->assertNotNull($requester);

        $this->buyer();

        return (string) PurchaseRequestItem::where('purchase_request_id', $id)->value('id');
    }

    public function test_order_is_created_with_backend_totals_and_ignores_sent_ones(): void
    {
        $this->buyer();

        $response = $this->postJson(self::BASE, $this->payload([
            'total' => '1.00',
            'tax_total' => '0.01',
            'items' => [[
                'item_presentation_id' => $this->productPresentation->id,
                'quantity' => '10',
                'unit_price' => '100',
                'discount_percent' => '5',
                'line_total' => '1.00',
            ]],
        ]))->assertCreated();

        $response->assertJsonPath('data.number', 'OC-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.tax_rate', '18.00')
            ->assertJsonPath('data.taxable_base', '950.00')
            ->assertJsonPath('data.discount_total', '50.00')
            ->assertJsonPath('data.tax_total', '171.00')
            ->assertJsonPath('data.total', '1121.00')
            ->assertJsonPath('data.total_base_currency', '1121.00')
            ->assertJsonPath('data.items.0.line_total', '1121.00')
            ->assertJsonPath('data.currency.code', 'PEN')
            ->assertJsonPath('data.provider.business_name', 'Distribuidora Andina S.A.C.');
    }

    public function test_prices_including_tax_extract_the_base(): void
    {
        $this->buyer();

        $this->postJson(self::BASE, $this->payload([
            'prices_include_tax' => true,
            'items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '1', 'unit_price' => '118']],
        ]))->assertCreated()
            ->assertJsonPath('data.taxable_base', '100.00')
            ->assertJsonPath('data.tax_total', '18.00')
            ->assertJsonPath('data.total', '118.00');
    }

    public function test_prices_include_tax_defaults_to_the_company_setting(): void
    {
        $this->buyer();
        CompanySetting::query()->firstOrCreate([], ['legal_name' => 'Mi Empresa'])->update(['prices_include_tax' => true, 'default_tax_rate' => 10]);

        $payload = $this->payload(['items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '1', 'unit_price' => '110']]]);
        unset($payload['prices_include_tax']);

        $this->postJson(self::BASE, $payload)->assertCreated()
            ->assertJsonPath('data.prices_include_tax', true)
            ->assertJsonPath('data.tax_rate', '10.00')
            ->assertJsonPath('data.total', '110.00');
    }

    public function test_tax_rate_is_frozen_on_the_document(): void
    {
        $this->buyer();
        $id = $this->createOrder();

        CompanySetting::query()->first()->update(['default_tax_rate' => 5]);

        $this->getJson(self::BASE."/{$id}")->assertOk()->assertJsonPath('data.tax_rate', '18.00')->assertJsonPath('data.tax_total', '180.00');
    }

    public function test_foreign_currency_uses_the_sell_rate_by_default_and_converts_the_base_total(): void
    {
        $this->buyer();

        $this->postJson(self::BASE, $this->payload(['currency_id' => $this->usd->id]))->assertCreated()
            ->assertJsonPath('data.exchange_rate', '3.8000')
            ->assertJsonPath('data.total', '1180.00')
            ->assertJsonPath('data.total_base_currency', '4484.00');

        $this->postJson(self::BASE, $this->payload(['currency_id' => $this->usd->id, 'exchange_rate' => '3.9']))->assertCreated()
            ->assertJsonPath('data.exchange_rate', '3.9000')
            ->assertJsonPath('data.total_base_currency', '4602.00');
    }

    public function test_foreign_currency_without_any_rate_is_rejected(): void
    {
        $this->buyer();
        $eur = Currency::factory()->create(['code' => 'EUR']);

        $this->postJson(self::BASE, $this->payload(['currency_id' => $eur->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('exchange_rate');

        $this->postJson(self::BASE, $this->payload(['currency_id' => $eur->id, 'exchange_rate' => '4.1']))->assertCreated();
    }

    public function test_base_currency_always_uses_rate_one(): void
    {
        $this->buyer();

        $this->postJson(self::BASE, $this->payload(['exchange_rate' => '9.99']))->assertCreated()
            ->assertJsonPath('data.exchange_rate', '1.0000');
    }

    public function test_credit_condition_requires_days_and_cash_resets_them(): void
    {
        $this->buyer();

        $this->postJson(self::BASE, $this->payload(['payment_condition' => 'credit']))
            ->assertUnprocessable()->assertJsonValidationErrors('credit_days');

        $this->postJson(self::BASE, $this->payload(['payment_condition' => 'credit', 'credit_days' => 45]))->assertCreated()
            ->assertJsonPath('data.credit_days', 45);

        $this->postJson(self::BASE, $this->payload(['payment_condition' => 'cash', 'credit_days' => 45]))->assertCreated()
            ->assertJsonPath('data.credit_days', 0);
    }

    public function test_free_text_lines_are_services_and_not_allowed_for_catalog_products(): void
    {
        $this->buyer();

        $this->postJson(self::BASE, $this->payload(['items' => [
            ['description' => 'Instalación de andamios', 'quantity' => '1', 'unit_price' => '500'],
        ]]))->assertCreated()
            ->assertJsonPath('data.items.0.is_service', true)
            ->assertJsonPath('data.items.0.description', 'Instalación de andamios')
            ->assertJsonPath('data.items.0.catalog_item_id', null);

        // Línea libre que reemplaza a un producto de un requerimiento → 422.
        $requestItem = $this->approvedRequestItem();
        $this->postJson(self::BASE, $this->payload(['items' => [
            ['description' => 'Cemento (texto libre)', 'quantity' => '1', 'unit_price' => '30', 'purchase_request_item_id' => $requestItem],
        ]]))->assertUnprocessable()->assertJsonValidationErrors('items.0.description');
    }

    public function test_catalog_service_line_is_flagged_as_service_with_its_tax_type(): void
    {
        $this->buyer();
        $this->service->update(['tax_type' => 'exempt']);

        $this->postJson(self::BASE, $this->payload(['items' => [
            ['item_presentation_id' => $this->servicePresentation->id, 'quantity' => '1', 'unit_price' => '200'],
        ]]))->assertCreated()
            ->assertJsonPath('data.items.0.is_service', true)
            ->assertJsonPath('data.items.0.tax_type', 'exempt')
            ->assertJsonPath('data.exempt_base', '200.00')
            ->assertJsonPath('data.tax_total', '0.00');
    }

    public function test_line_tax_type_can_be_overridden(): void
    {
        $this->buyer();

        $this->postJson(self::BASE, $this->payload(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '1', 'unit_price' => '100', 'tax_type' => 'unaffected'],
        ]]))->assertCreated()
            ->assertJsonPath('data.unaffected_base', '100.00')
            ->assertJsonPath('data.total', '100.00');
    }

    public function test_non_purchasable_item_inactive_provider_and_foreign_warehouse_are_rejected(): void
    {
        $this->buyer();

        $this->postJson(self::BASE, $this->payload(['warehouse_id' => $this->otherWarehouse->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('warehouse_id');

        $inactive = Provider::factory()->inactive()->create();
        $this->postJson(self::BASE, $this->payload(['provider_id' => $inactive->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('provider_id');

        CatalogItem::whereKey($this->product->id)->update(['is_purchasable' => false]);
        $this->postJson(self::BASE, $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.item_presentation_id');

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_order_validation_rules(): void
    {
        $this->buyer();

        $this->postJson(self::BASE, $this->payload(['items' => []]))->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson(self::BASE, $this->payload(['expected_date' => now()->subDay()->toDateString()]))->assertUnprocessable()->assertJsonValidationErrors('expected_date');
        $this->postJson(self::BASE, $this->payload(['items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '1', 'unit_price' => '-5']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.unit_price');
        $this->postJson(self::BASE, $this->payload(['items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '1', 'unit_price' => '5', 'discount_percent' => 120]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.discount_percent');
        $this->postJson(self::BASE, $this->payload(['items' => [['quantity' => '1', 'unit_price' => '5']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.description');
    }

    public function test_linked_line_cannot_exceed_the_pending_quantity_of_the_request(): void
    {
        $requestItem = $this->approvedRequestItem('10');

        $this->postJson(self::BASE, $this->payload(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '11', 'unit_price' => '10', 'purchase_request_item_id' => $requestItem],
        ]]))->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');

        // Dos líneas que sumadas superan el saldo.
        $this->postJson(self::BASE, $this->payload(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '6', 'unit_price' => '10', 'purchase_request_item_id' => $requestItem],
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '5', 'unit_price' => '10', 'purchase_request_item_id' => $requestItem],
        ]]))->assertUnprocessable();

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_request_ordered_quantities_and_status_follow_the_orders(): void
    {
        $requestItem = $this->approvedRequestItem('10');
        $requestId = PurchaseRequestItem::find($requestItem)->purchase_request_id;

        $first = $this->createOrder(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '4', 'unit_price' => '10', 'purchase_request_item_id' => $requestItem],
        ]]);

        $this->assertEquals(4, PurchaseRequestItem::find($requestItem)->ordered_quantity);
        $this->assertSame('partially_ordered', PurchaseRequest::find($requestId)->status->value);

        // Otra OC no puede pasar del saldo (6).
        $this->postJson(self::BASE, $this->payload(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '7', 'unit_price' => '10', 'purchase_request_item_id' => $requestItem],
        ]]))->assertUnprocessable();

        $this->createOrder(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '6', 'unit_price' => '10', 'purchase_request_item_id' => $requestItem],
        ]]);

        $this->assertEquals(10, PurchaseRequestItem::find($requestItem)->ordered_quantity);
        $this->assertSame('ordered', PurchaseRequest::find($requestId)->status->value);
        $this->assertCount(0, $this->getJson(self::REQUESTS.'/pending-items?warehouse_id='.$this->warehouse->id)->json('data'));

        // Editar la primera para que pida menos libera saldo.
        $this->putJson(self::BASE."/{$first}", $this->payload(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '2', 'unit_price' => '10', 'purchase_request_item_id' => $requestItem],
        ]]))->assertOk();

        $this->assertEquals(8, PurchaseRequestItem::find($requestItem)->ordered_quantity);
        $this->assertSame('partially_ordered', PurchaseRequest::find($requestId)->status->value);
    }

    public function test_editing_a_draft_with_its_own_reserved_quantity_is_allowed(): void
    {
        $requestItem = $this->approvedRequestItem('10');

        $id = $this->createOrder(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '10', 'unit_price' => '10', 'purchase_request_item_id' => $requestItem],
        ]]);

        $this->putJson(self::BASE."/{$id}", $this->payload(['notes' => 'Editada', 'items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '10', 'unit_price' => '12', 'purchase_request_item_id' => $requestItem],
        ]]))->assertOk()->assertJsonPath('data.notes', 'Editada');

        $this->assertEquals(10, PurchaseRequestItem::find($requestItem)->ordered_quantity);
    }

    public function test_request_must_be_approved_to_be_linked(): void
    {
        $this->actAs(['purchase_requests.view', 'purchase_requests.create']);
        $id = $this->postJson(self::REQUESTS, [
            'area_id' => $this->area->id,
            'warehouse_id' => $this->warehouse->id,
            'required_date' => now()->addDay()->toDateString(),
            'priority' => 'normal',
            'items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '5']],
        ])->json('data.id');
        $draftItem = PurchaseRequestItem::where('purchase_request_id', $id)->value('id');

        $this->buyer();
        $this->postJson(self::BASE, $this->payload(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '1', 'unit_price' => '10', 'purchase_request_item_id' => $draftItem],
        ]]))->assertUnprocessable()->assertJsonValidationErrors('items.0.purchase_request_item_id');
    }

    public function test_submit_under_the_threshold_approves_and_over_it_requires_approval(): void
    {
        $this->buyer();

        $small = $this->createOrder(['items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '1', 'unit_price' => '100']]]);
        $this->postJson(self::BASE."/{$small}/submit")->assertOk()->assertJsonPath('data.status', 'approved');

        // 10 × 1000 + IGV = 11 800 > 5 000 (umbral por defecto)
        $big = $this->createOrder(['items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '10', 'unit_price' => '1000']]]);
        $this->postJson(self::BASE."/{$big}/submit")->assertOk()->assertJsonPath('data.status', 'pending_approval');

        $this->postJson(self::BASE."/{$big}/submit")->assertUnprocessable();
    }

    public function test_threshold_is_compared_in_base_currency_and_is_configurable(): void
    {
        $this->buyer();
        (new SystemParameterSeeder)->run();
        $param = SystemParameter::where('group_key', 'purchases')->where('item_key', 'order_approval_threshold')->firstOrFail();
        app(SystemParameterService::class)->update($param, 1000);

        // 100 USD + IGV = 118 USD → 448.40 PEN (≤ 1 000)
        $usd = $this->createOrder(['currency_id' => $this->usd->id, 'items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '1', 'unit_price' => '100']]]);
        $this->postJson(self::BASE."/{$usd}/submit")->assertOk()->assertJsonPath('data.status', 'approved');

        // 300 USD + IGV = 354 USD → 1 345.20 PEN (> 1 000)
        $big = $this->createOrder(['currency_id' => $this->usd->id, 'items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '3', 'unit_price' => '100']]]);
        $this->postJson(self::BASE."/{$big}/submit")->assertOk()->assertJsonPath('data.status', 'pending_approval');
    }

    private function pendingOrder(): string
    {
        $this->buyer();
        $id = $this->createOrder(['items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '10', 'unit_price' => '1000']]]);
        $this->postJson(self::BASE."/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'pending_approval');

        return $id;
    }

    public function test_creator_cannot_approve_but_another_approver_can(): void
    {
        $id = $this->pendingOrder();

        // Quien creó la orden no puede aprobarla aunque tenga el permiso.
        $creator = $this->actAs(['purchase_orders.view', 'purchase_orders.approve']);
        PurchaseOrder::whereKey($id)->update(['created_by' => $creator->id]);
        $this->postJson(self::BASE."/{$id}/approve")->assertUnprocessable();

        $approver = $this->actAs(['purchase_orders.view', 'purchase_orders.approve']);
        $this->postJson(self::BASE."/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approver.id', $approver->id);
    }

    public function test_reject_requires_a_reason_releases_quantities_and_allows_editing_back_to_draft(): void
    {
        $requestItem = $this->approvedRequestItem('10');
        $requestId = PurchaseRequestItem::find($requestItem)->purchase_request_id;

        $id = $this->createOrder(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '10', 'unit_price' => '1000', 'purchase_request_item_id' => $requestItem],
        ]]);
        $this->postJson(self::BASE."/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'pending_approval');
        $this->assertSame('ordered', PurchaseRequest::find($requestId)->status->value);

        $this->actAs(['purchase_orders.approve']);
        $this->postJson(self::BASE."/{$id}/reject")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson(self::BASE."/{$id}/reject", ['reason' => 'Precio fuera de mercado'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Precio fuera de mercado');

        $this->assertEquals(0, PurchaseRequestItem::find($requestItem)->ordered_quantity);
        $this->assertSame('approved', PurchaseRequest::find($requestId)->status->value);

        $this->buyer();
        $this->putJson(self::BASE."/{$id}", $this->payload(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '4', 'unit_price' => '900', 'purchase_request_item_id' => $requestItem],
        ]]))->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.rejection_reason', null);

        $this->assertEquals(4, PurchaseRequestItem::find($requestItem)->ordered_quantity);
    }

    public function test_only_draft_or_rejected_orders_are_editable(): void
    {
        $this->buyer();
        $id = $this->createOrder(['items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '1', 'unit_price' => '10']]]);
        $this->postJson(self::BASE."/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'approved');

        $this->putJson(self::BASE."/{$id}", $this->payload())->assertUnprocessable()->assertJsonPath('errors.status.0', 'Solo se puede editar una orden en borrador o rechazada');
    }

    public function test_cancel_is_blocked_with_receptions_and_releases_request_quantities_otherwise(): void
    {
        $requestItem = $this->approvedRequestItem('10');

        $id = $this->createOrder(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '10', 'unit_price' => '10', 'purchase_request_item_id' => $requestItem],
        ]]);
        $this->postJson(self::BASE."/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'approved');

        PurchaseOrderItem::where('purchase_order_id', $id)->update(['received_quantity' => 3]);
        $this->actAs(['purchase_orders.cancel']);
        $this->postJson(self::BASE."/{$id}/cancel", ['reason' => 'Error de digitación'])->assertUnprocessable();

        PurchaseOrderItem::where('purchase_order_id', $id)->update(['received_quantity' => 0]);
        $this->postJson(self::BASE."/{$id}/cancel")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson(self::BASE."/{$id}/cancel", ['reason' => 'Error de digitación'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Error de digitación');

        $this->assertEquals(0, PurchaseRequestItem::find($requestItem)->ordered_quantity);
        $this->postJson(self::BASE."/{$id}/cancel", ['reason' => 'Otra vez'])->assertUnprocessable();
    }

    public function test_draft_order_can_be_cancelled_and_request_with_active_order_cannot(): void
    {
        $requestItem = $this->approvedRequestItem('10');
        $requestId = PurchaseRequestItem::find($requestItem)->purchase_request_id;

        $id = $this->createOrder(['items' => [
            ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '5', 'unit_price' => '10', 'purchase_request_item_id' => $requestItem],
        ]]);

        $this->actAs(['purchase_requests.approve', 'purchase_orders.cancel']);
        $this->postJson(self::REQUESTS."/{$requestId}/cancel", ['reason' => 'Ya no se necesita'])->assertUnprocessable();

        $this->postJson(self::BASE."/{$id}/cancel", ['reason' => 'Cambio de proveedor'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson(self::REQUESTS."/{$requestId}/cancel", ['reason' => 'Ya no se necesita'])->assertOk();
    }

    public function test_close_only_applies_to_approved_or_partially_received_orders(): void
    {
        $this->buyer();
        $id = $this->createOrder(['items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '10', 'unit_price' => '10']]]);

        $this->actAs(['purchase_orders.cancel']);
        $this->postJson(self::BASE."/{$id}/close")->assertUnprocessable();

        $this->buyer();
        $this->postJson(self::BASE."/{$id}/submit")->assertOk();

        PurchaseOrderItem::where('purchase_order_id', $id)->update(['received_quantity' => 4]);
        app(PurchaseOrderWorkflowService::class)->markReceived(PurchaseOrder::find($id));
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, PurchaseOrder::find($id)->status);

        $this->actAs(['purchase_orders.cancel']);
        $this->postJson(self::BASE."/{$id}/close", ['reason' => 'El proveedor no entregará el saldo'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->postJson(self::BASE."/{$id}/cancel", ['reason' => 'No aplica ya'])->assertUnprocessable();
    }

    public function test_mark_received_sets_partially_received_then_received(): void
    {
        $this->buyer();
        $id = $this->createOrder(['items' => [['item_presentation_id' => $this->productPresentation->id, 'quantity' => '10', 'unit_price' => '10']]]);
        $this->postJson(self::BASE."/{$id}/submit")->assertOk();
        $workflow = app(PurchaseOrderWorkflowService::class);

        $workflow->markReceived(PurchaseOrder::find($id));
        $this->assertSame(PurchaseOrderStatus::Approved, PurchaseOrder::find($id)->status);

        PurchaseOrderItem::where('purchase_order_id', $id)->update(['received_quantity' => 6]);
        $workflow->markReceived(PurchaseOrder::find($id));
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, PurchaseOrder::find($id)->status);

        PurchaseOrderItem::where('purchase_order_id', $id)->update(['received_quantity' => 10]);
        $workflow->markReceived(PurchaseOrder::find($id));
        $this->assertSame(PurchaseOrderStatus::Received, PurchaseOrder::find($id)->status);
    }

    public function test_pdf_is_generated_for_the_order(): void
    {
        $this->buyer();
        $id = $this->createOrder();

        $response = $this->get(self::BASE."/{$id}/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('OC-000001.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        $inline = $this->get(self::BASE."/{$id}/pdf?inline=1");
        $this->assertStringContainsString('inline', (string) $inline->headers->get('Content-Disposition'));
    }

    public function test_pdf_view_data_carries_the_watermark_until_the_order_is_issued(): void
    {
        $this->buyer();
        $id = $this->createOrder();
        $builder = app(PurchaseOrderPdfBuilder::class);

        $this->assertSame('BORRADOR', $builder->build(PurchaseOrder::find($id))['watermark']);

        $this->postJson(self::BASE."/{$id}/submit")->assertOk();
        $data = $builder->build(PurchaseOrder::find($id));
        $this->assertNull($data['watermark']);
        $this->assertSame('Cemento Sol (Bolsa 42.5 kg)', $data['items'][0]['description']);
        $this->assertSame('1180.00', $data['totals']['total']);

        $html = view('pdf.purchases.order', array_merge($data, [
            'company' => CompanySetting::query()->firstOrCreate([], ['legal_name' => 'Mi Empresa S.A.C.']),
            'logo_data_uri' => null,
        ]))->render();
        $this->assertStringContainsString('ORDEN DE COMPRA', $html);
        $this->assertStringContainsString('OC-000001', $html);
        $this->assertStringContainsString('Distribuidora Andina S.A.C.', $html);
        $this->assertStringContainsString('Pago contra entrega', $html);
        $this->assertStringNotContainsString('BORRADOR', $html);
    }

    public function test_listing_filters(): void
    {
        $this->buyer();
        $first = $this->createOrder();
        $other = Provider::factory()->create(['business_name' => 'Ferretería Central']);
        $this->createOrder(['provider_id' => $other->id, 'order_date' => now()->subDays(10)->toDateString(), 'expected_date' => null]);
        $this->postJson(self::BASE."/{$first}/submit")->assertOk();

        $this->getJson(self::BASE)->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson(self::BASE.'?status=approved')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'?provider_id='.$other->id)->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'?search=Ferreter')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'?search=OC-000001')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'?date_from='.now()->subDays(2)->toDateString())->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'?date_to='.now()->subDays(5)->toDateString())->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_orders_of_an_inaccessible_branch_are_not_found(): void
    {
        $this->buyer();
        $id = $this->createOrder();
        PurchaseOrder::whereKey($id)->update(['branch_id' => $this->otherBranch->id]);

        $this->getJson(self::BASE."/{$id}")->assertNotFound();
        $this->putJson(self::BASE."/{$id}", $this->payload())->assertNotFound();
        $this->postJson(self::BASE."/{$id}/submit")->assertNotFound();
        $this->getJson(self::BASE."/{$id}/pdf")->assertNotFound();
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_permissions_are_enforced(): void
    {
        $this->actAs(['purchase_orders.view']);
        $this->getJson(self::BASE)->assertOk();
        $this->postJson(self::BASE, $this->payload())->assertForbidden();

        $this->buyer();
        $id = $this->createOrder();
        $this->actAs(['purchase_orders.view']);
        $this->putJson(self::BASE."/{$id}", $this->payload())->assertForbidden();
        $this->postJson(self::BASE."/{$id}/submit")->assertForbidden();
        $this->postJson(self::BASE."/{$id}/approve")->assertForbidden();
        $this->postJson(self::BASE."/{$id}/reject", ['reason' => 'Motivo válido'])->assertForbidden();
        $this->postJson(self::BASE."/{$id}/close")->assertForbidden();
        $this->postJson(self::BASE."/{$id}/cancel", ['reason' => 'Motivo válido'])->assertForbidden();

        $this->actAs([]);
        $this->getJson(self::BASE)->assertForbidden();
        $this->getJson(self::BASE."/{$id}/pdf")->assertForbidden();
    }

    public function test_provider_and_catalog_item_with_orders_cannot_be_deleted(): void
    {
        $this->buyer();
        $this->createOrder();

        $this->actAs(['providers.view', 'providers.delete']);
        $this->deleteJson('/api/v1/purchases/providers/'.$this->provider->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.provider.0', 'No se puede eliminar un proveedor con órdenes o facturas; desactívelo en su lugar');

        $this->assertThrows(
            fn () => app(CatalogItemService::class)->delete($this->product),
            InventoryRuleException::class,
        );
        $this->assertNotNull(CatalogItem::find($this->product->id));
    }

    public function test_numbers_are_sequential(): void
    {
        $this->buyer();

        $this->postJson(self::BASE, $this->payload())->assertCreated()->assertJsonPath('data.number', 'OC-000001');
        $this->postJson(self::BASE, $this->payload())->assertCreated()->assertJsonPath('data.number', 'OC-000002');
    }
}
