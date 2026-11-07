<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Purchases;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Maintenance\Models\Area;
use App\Modules\Purchases\Models\PurchaseRequest;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpPurchasing;
use Tests\TestCase;

class PurchaseRequestsApiTest extends TestCase
{
    use RefreshDatabase, SetsUpPurchasing;

    private const BASE = '/api/v1/purchases/requests';

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
            'area_id' => $this->area->id,
            'warehouse_id' => $this->warehouse->id,
            'required_date' => now()->addDays(5)->toDateString(),
            'priority' => 'normal',
            'notes' => 'Para la obra norte',
            'items' => [
                ['item_presentation_id' => $this->productPresentation->id, 'quantity' => '10'],
                ['description' => 'Alquiler de andamio', 'quantity' => '2', 'notes' => 'Por semana'],
            ],
        ], $overrides);
    }

    private function requester(): array
    {
        return ['purchase_requests.view', 'purchase_requests.create'];
    }

    public function test_request_is_created_as_draft_with_number_and_lines(): void
    {
        $this->actAs($this->requester());

        $response = $this->postJson(self::BASE, $this->payload())->assertCreated();

        $response->assertJsonPath('data.number', 'RQ-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.priority', 'normal')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.line', 1)
            ->assertJsonPath('data.items.0.catalog_item_id', $this->product->id)
            ->assertJsonPath('data.items.0.quantity', '10.0000')
            ->assertJsonPath('data.items.0.pending_quantity', '10.0000')
            ->assertJsonPath('data.items.1.description', 'Alquiler de andamio')
            ->assertJsonPath('data.items.1.catalog_item_id', null);

        $this->postJson(self::BASE, $this->payload())->assertCreated()->assertJsonPath('data.number', 'RQ-000002');
    }

    public function test_request_validation_rules(): void
    {
        $this->actAs($this->requester());

        $this->postJson(self::BASE, $this->payload(['required_date' => now()->subDay()->toDateString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('required_date');

        $this->postJson(self::BASE, $this->payload(['area_id' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');

        $this->postJson(self::BASE, $this->payload(['items' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->postJson(self::BASE, $this->payload(['items' => [['quantity' => '1']]]))
            ->assertUnprocessable()->assertJsonValidationErrors(['items.0.item_presentation_id', 'items.0.description']);

        $this->postJson(self::BASE, $this->payload(['items' => [['description' => 'Algo', 'quantity' => '0']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');

        $this->postJson(self::BASE, $this->payload(['priority' => 'inmediata']))
            ->assertUnprocessable()->assertJsonValidationErrors('priority');

        $this->assertDatabaseCount('purchase_requests', 0);
    }

    public function test_warehouse_must_belong_to_the_active_branch(): void
    {
        $this->actAs($this->requester());

        $this->postJson(self::BASE, $this->payload(['warehouse_id' => $this->otherWarehouse->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('warehouse_id');
    }

    public function test_non_purchasable_or_inactive_items_are_rejected(): void
    {
        $this->actAs($this->requester());
        $this->product->update(['is_purchasable' => false]);

        $this->postJson(self::BASE, $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.item_presentation_id');
    }

    public function test_request_goes_through_submit_and_approve(): void
    {
        $creator = $this->actAs($this->requester());
        $id = $this->postJson(self::BASE, $this->payload())->json('data.id');

        $this->postJson(self::BASE."/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->actAs(['purchase_requests.view', 'purchase_requests.approve']);
        $this->postJson(self::BASE."/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertNotNull(PurchaseRequest::find($id)->approved_at);
        $this->assertNotSame($creator->id, PurchaseRequest::find($id)->approved_by);
    }

    public function test_approve_only_works_from_submitted(): void
    {
        $this->actAs($this->requester());
        $id = $this->postJson(self::BASE, $this->payload())->json('data.id');

        $this->actAs(['purchase_requests.approve']);
        $this->postJson(self::BASE."/{$id}/approve")->assertUnprocessable();
        $this->postJson(self::BASE."/{$id}/reject", ['reason' => 'No corresponde'])->assertUnprocessable();
    }

    public function test_reject_requires_a_reason_and_is_final(): void
    {
        $this->actAs($this->requester());
        $id = $this->postJson(self::BASE, $this->payload())->json('data.id');
        $this->postJson(self::BASE."/{$id}/submit")->assertOk();

        $this->actAs(['purchase_requests.approve']);
        $this->postJson(self::BASE."/{$id}/reject")->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson(self::BASE."/{$id}/reject", ['reason' => 'Presupuesto agotado'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Presupuesto agotado');

        $this->postJson(self::BASE."/{$id}/approve")->assertUnprocessable();
    }

    public function test_only_the_creator_can_edit_or_submit_a_draft(): void
    {
        $this->actAs($this->requester());
        $id = $this->postJson(self::BASE, $this->payload())->json('data.id');

        $this->actAs($this->requester());
        $this->putJson(self::BASE."/{$id}", $this->payload())->assertForbidden();
        $this->postJson(self::BASE."/{$id}/submit")->assertForbidden();
    }

    public function test_creator_can_edit_the_draft_and_lines_are_replaced(): void
    {
        $this->actAs($this->requester());
        $id = $this->postJson(self::BASE, $this->payload())->json('data.id');

        $this->putJson(self::BASE."/{$id}", $this->payload([
            'priority' => 'urgent',
            'items' => [['item_presentation_id' => $this->servicePresentation->id, 'quantity' => '1']],
        ]))->assertOk()
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.catalog_item_id', $this->service->id);

        $this->assertDatabaseCount('purchase_request_items', 1);
    }

    public function test_submitted_request_cannot_be_edited(): void
    {
        $this->actAs($this->requester());
        $id = $this->postJson(self::BASE, $this->payload())->json('data.id');
        $this->postJson(self::BASE."/{$id}/submit")->assertOk();

        $this->putJson(self::BASE."/{$id}", $this->payload())->assertUnprocessable();
    }

    public function test_request_without_orders_can_be_cancelled(): void
    {
        $this->actAs($this->requester());
        $id = $this->postJson(self::BASE, $this->payload())->json('data.id');
        $this->postJson(self::BASE."/{$id}/submit")->assertOk();

        $this->actAs(['purchase_requests.approve']);
        $this->postJson(self::BASE."/{$id}/cancel")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson(self::BASE."/{$id}/cancel", ['reason' => 'Ya no se necesita'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Ya no se necesita');
    }

    public function test_from_low_stock_creates_a_draft_with_suggested_quantities(): void
    {
        $this->actAs($this->requester());

        $stock = Stock::create(['warehouse_id' => $this->warehouse->id, 'catalog_item_id' => $this->product->id, 'min_stock' => 20, 'max_stock' => 50]);
        $stock->forceFill(['quantity' => 5])->save();

        $okItem = CatalogItem::factory()->create(['is_purchasable' => true]);
        ItemPresentation::factory()->create(['catalog_item_id' => $okItem->id, 'unit_id' => $okItem->base_unit_id, 'is_default' => true]);
        $fine = Stock::create(['warehouse_id' => $this->warehouse->id, 'catalog_item_id' => $okItem->id, 'min_stock' => 5]);
        $fine->forceFill(['quantity' => 30])->save();

        $response = $this->postJson(self::BASE.'/from-low-stock', [
            'warehouse_id' => $this->warehouse->id,
            'area_id' => $this->area->id,
        ])->assertCreated();

        $response->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.item_presentation_id', $this->productPresentation->id);

        // max(max_stock 50, min_stock 20) - cantidad 5 = 45
        $this->assertEquals(45, (float) $response->json('data.items.0.quantity'));
    }

    public function test_from_low_stock_without_items_below_minimum_is_rejected(): void
    {
        $this->actAs($this->requester());
        $stock = Stock::create(['warehouse_id' => $this->warehouse->id, 'catalog_item_id' => $this->product->id, 'min_stock' => 5]);
        $stock->forceFill(['quantity' => 50])->save();

        $this->postJson(self::BASE.'/from-low-stock', ['warehouse_id' => $this->warehouse->id, 'area_id' => $this->area->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No hay ítems por debajo del stock mínimo');

        $this->assertDatabaseCount('purchase_requests', 0);
    }

    public function test_pending_items_lists_only_approved_lines_with_balance(): void
    {
        $this->actAs($this->requester());

        $approved = $this->postJson(self::BASE, $this->payload())->json('data.id');
        $this->postJson(self::BASE."/{$approved}/submit");
        $draft = $this->postJson(self::BASE, $this->payload())->json('data.id');

        $this->actAs(['purchase_requests.approve']);
        $this->postJson(self::BASE."/{$approved}/approve")->assertOk();

        $this->actAs(['purchase_orders.create']);
        $response = $this->getJson(self::BASE.'/pending-items?warehouse_id='.$this->warehouse->id)->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame('RQ-000001', $response->json('data.0.request.number'));
        $this->assertNotContains($draft, array_column(array_column($response->json('data'), 'request'), 'id'));

        $this->getJson(self::BASE.'/pending-items')->assertUnprocessable();
    }

    public function test_listing_filters_by_status_and_ownership(): void
    {
        $me = $this->actAs($this->requester());
        $mine = $this->postJson(self::BASE, $this->payload())->json('data.id');
        $this->postJson(self::BASE."/{$mine}/submit");

        $this->postJson(self::BASE, $this->payload())->assertCreated();

        $other = $this->actAs($this->requester());
        $this->postJson(self::BASE, $this->payload(['area_id' => Area::factory()->create()->id]))->assertCreated();

        $this->getJson(self::BASE)->assertOk()->assertJsonPath('meta.total', 3);
        $this->getJson(self::BASE.'?mine=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'?status=submitted')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::BASE.'?search=RQ-000002')->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertNotSame($me->id, $other->id);
    }

    public function test_requests_of_an_inaccessible_branch_are_not_found(): void
    {
        $this->actAs($this->requester());
        $foreign = PurchaseRequest::create([
            'number' => 'RQ-009999',
            'branch_id' => $this->otherBranch->id,
            'area_id' => $this->area->id,
            'warehouse_id' => $this->otherWarehouse->id,
            'requested_by' => User::factory()->create()->id,
            'required_date' => now()->addDay(),
        ]);

        $this->getJson(self::BASE.'/'.$foreign->id)->assertNotFound();
        $this->postJson(self::BASE.'/'.$foreign->id.'/submit')->assertNotFound();
    }

    public function test_permissions_are_enforced(): void
    {
        $this->actAs(['purchase_requests.view']);
        $this->getJson(self::BASE)->assertOk();
        $this->postJson(self::BASE, $this->payload())->assertForbidden();
        $this->postJson(self::BASE.'/from-low-stock', ['warehouse_id' => $this->warehouse->id, 'area_id' => $this->area->id])->assertForbidden();

        $this->actAs([]);
        $this->getJson(self::BASE)->assertForbidden();

        $this->actAs($this->requester());
        $id = $this->postJson(self::BASE, $this->payload())->json('data.id');
        $this->postJson(self::BASE."/{$id}/approve")->assertForbidden();
        $this->postJson(self::BASE."/{$id}/reject", ['reason' => 'Motivo válido'])->assertForbidden();
        $this->postJson(self::BASE."/{$id}/cancel", ['reason' => 'Motivo válido'])->assertForbidden();
        $this->getJson(self::BASE.'/pending-items?warehouse_id='.$this->warehouse->id)->assertForbidden();
    }

    public function test_user_role_can_view_and_create_requests_but_not_approve(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('name', 'user')->firstOrFail());
        $user->branches()->sync([$this->branch->id => ['is_default' => true]]);
        $this->withHeader('X-Branch-Id', $this->branch->id);
        $this->withHeader('Authorization', 'Bearer '.app(JwtService::class)->createTokenPair($user)['access_token']);

        $id = $this->postJson(self::BASE, $this->payload())->assertCreated()->json('data.id');
        $this->postJson(self::BASE."/{$id}/submit")->assertOk();
        $this->postJson(self::BASE."/{$id}/approve")->assertForbidden();
    }
}
