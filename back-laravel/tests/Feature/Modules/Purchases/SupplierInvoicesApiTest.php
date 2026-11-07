<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Purchases;

use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Purchases\Models\PayableInstallment;
use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Models\SupplierPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpPayables;
use Tests\TestCase;

class SupplierInvoicesApiTest extends TestCase
{
    use RefreshDatabase, SetsUpPayables;

    private const BASE = '/api/v1/purchases/supplier-invoices';

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
     * @return array<string, mixed>
     */
    private function payloadFromReceipt(string $receiptId, string $documentNumber = '000123'): array
    {
        $this->actAs(['supplier_invoices.create']);
        $prefill = $this->getJson(self::BASE.'/prefill?'.http_build_query(['receipt_ids' => [$receiptId]]))->assertOk();

        /** @var list<array<string, mixed>> $lines */
        $lines = $prefill->json('data.items');

        return $this->invoicePayload([
            'document_number' => $documentNumber,
            'prices_include_tax' => false,
            'receipt_ids' => [$receiptId],
            'items' => array_map(fn (array $line) => [
                'description' => $line['description'],
                'item_presentation_id' => $line['item_presentation_id'],
                'purchase_receipt_item_id' => $line['purchase_receipt_item_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_percent' => $line['discount_percent'],
                'tax_type' => $line['tax_type'],
            ], $lines),
        ]);
    }

    public function test_prefill_returns_receipt_lines_valued_at_order_prices(): void
    {
        ['receipt_id' => $receiptId, 'order' => $order] = $this->receivedOrder();

        $this->actAs(['supplier_invoices.create']);
        $res = $this->getJson(self::BASE.'/prefill?'.http_build_query(['receipt_ids' => [$receiptId]]))->assertOk();

        $this->assertSame($this->provider->id, $res->json('data.provider.id'));
        $this->assertSame($this->pen->id, $res->json('data.currency.id'));
        $this->assertSame($order->id, $res->json('data.purchase_order.id'));
        $this->assertCount(2, $res->json('data.items'));
        $this->assertEquals('50.0000', $res->json('data.items.0.unit_price'));
        $this->assertEquals('10.0000', $res->json('data.items.0.quantity'));
    }

    public function test_purchase_invoice_from_receipt_creates_pending_debt_with_single_installment(): void
    {
        ['receipt_id' => $receiptId, 'order' => $order] = $this->receivedOrder();

        $res = $this->storeInvoice($this->payloadFromReceipt($receiptId))->assertCreated();

        $this->assertSame('FP-000001', $res->json('data.number'));
        $this->assertSame('pending', $res->json('data.status'));
        $this->assertEquals('826.00', $res->json('data.total'));
        $this->assertEquals('826.00', $res->json('data.balance'));
        $this->assertSame($order->id, $res->json('data.purchase_order_id'));
        $this->assertSame(now()->addDays(30)->toDateString(), $res->json('data.due_date'));
        $this->assertCount(1, $res->json('data.installments'));
        $this->assertEquals('826.00', $res->json('data.installments.0.amount'));
        $this->assertCount(1, $res->json('data.receipts'));
        $this->assertNull($res->json('meta.warnings'));

        // La recepción facturada deja de ofrecerse para facturar.
        $this->actAs(['purchase_receipts.view']);
        $this->getJson('/api/v1/purchases/receipts?status=confirmed&not_invoiced=1')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/purchases/receipts?status=confirmed')->assertOk()->assertJsonCount(1, 'data');

        // La recepción facturada ya no puede anularse.
        $this->actAs(['purchase_receipts.cancel']);
        $this->postJson("/api/v1/purchases/receipts/{$receiptId}/cancel", ['reason' => 'Error de registro'])
            ->assertStatus(422);
    }

    public function test_invoice_differing_from_receipts_returns_warning_without_blocking(): void
    {
        ['receipt_id' => $receiptId] = $this->receivedOrder();

        $payload = $this->payloadFromReceipt($receiptId);
        $payload['items'][0]['unit_price'] = '60';

        $res = $this->storeInvoice($payload)->assertCreated();

        $this->assertEquals('944.00', $res->json('data.total'));
        $this->assertCount(1, $res->json('meta.warnings'));
        $this->assertStringContainsString('difiere', (string) $res->json('meta.warnings.0'));
    }

    public function test_receipt_already_invoiced_is_rejected(): void
    {
        ['receipt_id' => $receiptId] = $this->receivedOrder();
        $payload = $this->payloadFromReceipt($receiptId, '000001');

        $this->storeInvoice($payload)->assertCreated();

        $this->storeInvoice(array_merge($payload, ['document_number' => '000002']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['receipt_ids']);
    }

    public function test_duplicate_provider_document_is_rejected_unless_previous_is_cancelled(): void
    {
        $first = $this->storeInvoice($this->invoicePayload(['document_number' => '000777']))->assertCreated();

        $this->storeInvoice($this->invoicePayload(['document_number' => '000777']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document_number']);

        $this->actAs(['supplier_invoices.cancel']);
        $this->postJson(self::BASE."/{$first->json('data.id')}/cancel", ['reason' => 'Registrada por error'])->assertOk();

        $this->storeInvoice($this->invoicePayload(['document_number' => '000777']))->assertCreated();
    }

    public function test_installments_must_add_up_to_total(): void
    {
        $this->storeInvoice($this->invoicePayload([
            'installments' => [
                ['due_date' => now()->addDays(15)->toDateString(), 'amount' => '40.00'],
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => '40.00'],
            ],
        ]))->assertStatus(422)->assertJsonValidationErrors(['installments']);

        $res = $this->storeInvoice($this->invoicePayload([
            'installments' => [
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => '40.00'],
                ['due_date' => now()->addDays(15)->toDateString(), 'amount' => '60.00'],
            ],
        ]))->assertCreated();

        $this->assertCount(2, $res->json('data.installments'));
        $this->assertSame(1, $res->json('data.installments.0.number'));
        $this->assertEquals('60.00', $res->json('data.installments.0.amount'));
        $this->assertSame(now()->addDays(15)->toDateString(), $res->json('data.installments.0.due_date'));
    }

    public function test_expense_without_provider_paid_now_in_cash_registers_cash_movement(): void
    {
        $this->actAsCashier(['supplier_invoices.create', 'supplier_payments.create']);

        $res = $this->postJson(self::BASE, $this->invoicePayload([
            'kind' => 'expense',
            'provider_id' => null,
            'counterparty_name' => 'Juan Pérez (movilidad)',
            'document_type' => 'invoice',
            'series' => null,
            'document_number' => 'REC-15',
            'concept_id' => $this->expenseConcept->id,
            'pay_now' => true,
            'payment' => [
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->session->id,
            ],
        ], '35.50'))->assertCreated();

        $this->assertSame('expense', $res->json('data.kind'));
        $this->assertSame('other', $res->json('data.document_type'));
        $this->assertSame('paid', $res->json('data.status'));
        $this->assertEquals('0.00', $res->json('data.balance'));

        /** @var SupplierPayment $payment */
        $payment = SupplierPayment::firstOrFail();
        $this->assertSame('PP-000001', $payment->number);
        $this->assertSame('Juan Pérez (movilidad)', $payment->counterparty_name);

        /** @var CashMovement $movement */
        $movement = CashMovement::where('source_id', $payment->id)->firstOrFail();
        $this->assertSame($this->session->id, $movement->cash_session_id);
        $this->assertEquals('35.50', $movement->amount);
        $this->assertSame('out', $movement->direction instanceof \BackedEnum ? $movement->direction->value : $movement->direction);
    }

    public function test_mixed_payment_pays_down_payment_now_and_finances_remainder_in_two_installments(): void
    {
        $this->actAsCashier(['supplier_invoices.create', 'supplier_payments.create']);

        $res = $this->postJson(self::BASE, $this->invoicePayload([
            'kind' => 'expense',
            'provider_id' => null,
            'counterparty_name' => 'Juan Pérez (movilidad)',
            'document_type' => 'invoice',
            'series' => null,
            'document_number' => 'REC-16',
            'concept_id' => $this->expenseConcept->id,
            'pay_now' => true,
            'down_payment_amount' => '40.00',
            'installments' => [
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => '30.00'],
                ['due_date' => now()->addDays(15)->toDateString(), 'amount' => '30.00'],
            ],
            'payment' => [
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->session->id,
            ],
        ], '100.00'))->assertCreated();

        $this->assertSame('partially_paid', $res->json('data.status'));
        $this->assertEquals('40.00', $res->json('data.paid_amount'));
        $this->assertEquals('60.00', $res->json('data.balance'));

        $installments = $res->json('data.installments');
        $this->assertCount(3, $installments);
        $this->assertEquals('40.00', $installments[0]['amount']);
        $this->assertSame(now()->toDateString(), $installments[0]['due_date']);
        $this->assertEquals('30.00', $installments[1]['amount']);
        $this->assertSame(now()->addDays(15)->toDateString(), $installments[1]['due_date']);
        $this->assertEquals('30.00', $installments[2]['amount']);
        $this->assertSame(now()->addDays(30)->toDateString(), $installments[2]['due_date']);

        /** @var SupplierPayment $payment */
        $payment = SupplierPayment::firstOrFail();
        $this->assertEquals('40.00', $payment->amount);
    }

    public function test_down_payment_amount_cannot_exceed_total(): void
    {
        $this->actAsCashier(['supplier_invoices.create', 'supplier_payments.create']);

        $this->postJson(self::BASE, $this->invoicePayload([
            'kind' => 'expense',
            'provider_id' => null,
            'counterparty_name' => 'Juan Pérez (movilidad)',
            'document_type' => 'invoice',
            'series' => null,
            'document_number' => 'REC-17',
            'concept_id' => $this->expenseConcept->id,
            'pay_now' => true,
            'down_payment_amount' => '150.00',
            'payment' => [
                'payment_method_id' => $this->cashMethod->id,
                'cash_session_id' => $this->session->id,
            ],
        ], '100.00'))->assertStatus(422)->assertJsonValidationErrors(['down_payment_amount']);

        $this->assertSame(0, SupplierInvoice::count());
    }

    public function test_pay_now_requires_payment_permission(): void
    {
        $this->actAs(['supplier_invoices.create']);

        $this->postJson(self::BASE, $this->invoicePayload([
            'kind' => 'expense',
            'concept_id' => $this->expenseConcept->id,
            'pay_now' => true,
            'payment' => ['payment_method_id' => $this->cashMethod->id, 'cash_session_id' => $this->session->id],
        ]))->assertForbidden();

        $this->assertSame(0, SupplierInvoice::count());
    }

    public function test_pay_now_rolls_back_invoice_when_payment_fails(): void
    {
        $this->actAsCashier(['supplier_invoices.create', 'supplier_payments.create']);

        $this->postJson(self::BASE, $this->invoicePayload([
            'kind' => 'expense',
            'concept_id' => $this->expenseConcept->id,
            'pay_now' => true,
            'payment' => ['payment_method_id' => $this->cashMethod->id],
        ]))->assertStatus(422)->assertJsonValidationErrors(['cash_session_id']);

        $this->assertSame(0, SupplierInvoice::count());
    }

    public function test_expense_requires_non_system_expense_concept(): void
    {
        /** @var FinanceConcept $system */
        $system = FinanceConcept::where('code', 'SUPPLIER_PAYMENT')->firstOrFail();

        $this->storeInvoice($this->invoicePayload(['kind' => 'expense', 'concept_id' => $system->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['concept_id']);

        $this->storeInvoice($this->invoicePayload(['kind' => 'expense']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['concept_id']);
    }

    public function test_expense_concept_must_be_charged_to_an_expense_account(): void
    {
        $expenseConcept = fn (string $accountCode) => FinanceConcept::factory()->expense()->create([
            'financial_account_id' => FinancialAccount::where('code', $accountCode)->firstOrFail()->id,
        ]);

        $this->storeInvoice($this->invoicePayload(['kind' => 'expense', 'concept_id' => $expenseConcept('4511')->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['concept_id']);

        $this->storeInvoice($this->invoicePayload(['kind' => 'expense', 'concept_id' => $expenseConcept('6361')->id, 'document_number' => 'A-ELEC']))
            ->assertCreated();
    }

    public function test_expense_concept_without_account_is_rejected_when_account_is_required(): void
    {
        $this->storeInvoice($this->invoicePayload(['kind' => 'expense', 'concept_id' => $this->expenseConcept->id]))
            ->assertCreated();

        app(SystemParameterService::class)->update(
            SystemParameter::where('group_key', 'finance')->where('item_key', 'require_concept_account')->firstOrFail(),
            true
        );

        $this->storeInvoice($this->invoicePayload(['kind' => 'expense', 'concept_id' => $this->expenseConcept->id, 'document_number' => 'A-NOACC']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['concept_id']);
    }

    public function test_cancel_with_active_payments_is_rejected_and_cancel_frees_receipts(): void
    {
        ['receipt_id' => $receiptId] = $this->receivedOrder();
        $invoice = $this->storeInvoice($this->payloadFromReceipt($receiptId))->assertCreated();
        $invoiceId = (string) $invoice->json('data.id');

        /** @var PayableInstallment $installment */
        $installment = PayableInstallment::where('supplier_invoice_id', $invoiceId)->firstOrFail();

        $this->actAs(['supplier_payments.create', 'supplier_payments.cancel']);
        $payment = $this->postJson('/api/v1/purchases/supplier-payments', [
            'provider_id' => $this->provider->id,
            'payment_date' => now()->toDateString(),
            'currency_id' => $this->pen->id,
            'payment_method_id' => $this->transferMethod->id,
            'allocations' => [['installment_id' => $installment->id, 'amount' => '100.00']],
        ])->assertCreated();

        $this->actAs(['supplier_invoices.cancel']);
        $this->postJson(self::BASE."/{$invoiceId}/cancel", ['reason' => 'Error'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Anule primero los pagos aplicados a esta factura');

        $this->actAs(['supplier_payments.cancel']);
        $this->postJson("/api/v1/purchases/supplier-payments/{$payment->json('data.id')}/cancel", ['reason' => 'Error'])->assertOk();

        $this->actAs(['supplier_invoices.cancel', 'supplier_invoices.view']);
        $this->postJson(self::BASE."/{$invoiceId}/cancel", ['reason' => 'Error'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $installment->fresh()?->status->value);

        $this->actAs(['purchase_receipts.cancel']);
        $this->postJson("/api/v1/purchases/receipts/{$receiptId}/cancel", ['reason' => 'Devolución total'])->assertOk();
    }

    public function test_attachment_is_stored_privately_and_downloaded_through_endpoint(): void
    {
        Storage::fake('local');

        $res = $this->storeInvoice($this->invoicePayload([
            'attachment' => UploadedFile::fake()->create('factura.pdf', 120, 'application/pdf'),
        ]))->assertCreated();

        $invoiceId = (string) $res->json('data.id');
        $this->assertStringEndsWith("/api/v1/purchases/supplier-invoices/{$invoiceId}/attachment", (string) $res->json('data.attachment_url'));

        /** @var SupplierInvoice $invoice */
        $invoice = SupplierInvoice::findOrFail($invoiceId);
        $this->assertNotNull($invoice->attachment_path);
        Storage::disk('local')->assertExists((string) $invoice->attachment_path);

        $this->actAs(['supplier_invoices.view']);
        $this->get(self::BASE."/{$invoiceId}/attachment")->assertOk();
    }

    public function test_index_lists_invoices_of_active_branch_with_filters(): void
    {
        $this->storeInvoice($this->invoicePayload(['document_number' => 'A-1']))->assertCreated();
        $this->storeInvoice($this->invoicePayload(['kind' => 'expense', 'concept_id' => $this->expenseConcept->id, 'document_number' => 'A-2']))->assertCreated();

        $this->actAs(['supplier_invoices.view']);
        $this->getJson(self::BASE)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(self::BASE.'?kind=expense')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.document_number', 'A-2');
    }

    public function test_actions_require_permissions(): void
    {
        $invoice = $this->storeInvoice($this->invoicePayload())->assertCreated();
        $id = (string) $invoice->json('data.id');

        $this->actAs([]);
        $this->getJson(self::BASE)->assertForbidden();
        $this->getJson(self::BASE."/{$id}")->assertForbidden();
        $this->getJson(self::BASE.'/prefill?receipt_ids[]='.$id)->assertForbidden();
        $this->postJson(self::BASE, $this->invoicePayload())->assertForbidden();
        $this->postJson(self::BASE."/{$id}/cancel", ['reason' => 'Sin permiso'])->assertForbidden();
        $this->get(self::BASE."/{$id}/attachment")->assertForbidden();
    }

    public function test_invoice_of_inaccessible_branch_returns_not_found(): void
    {
        $invoice = $this->storeInvoice($this->invoicePayload())->assertCreated();

        $user = $this->actAs(['supplier_invoices.view']);
        $user->branches()->sync([$this->otherBranch->id => ['is_default' => true]]);
        $this->withHeader('X-Branch-Id', $this->otherBranch->id);

        $this->getJson(self::BASE."/{$invoice->json('data.id')}")->assertNotFound();
    }
}
