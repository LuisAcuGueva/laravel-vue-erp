<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Purchases;

use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Purchases\Models\PayableInstallment;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\SupplierInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpPayables;
use Tests\TestCase;

class SupplierPaymentsApiTest extends TestCase
{
    use RefreshDatabase, SetsUpPayables;

    private const BASE = '/api/v1/purchases/supplier-payments';

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
     * Factura de 300.00 en dos cuotas (100 a 15 días, 200 a 30 días).
     *
     * @return array{invoice: SupplierInvoice, first: PayableInstallment, second: PayableInstallment}
     */
    private function invoiceWithTwoInstallments(string $documentNumber = '000100'): array
    {
        $res = $this->storeInvoice($this->invoicePayload([
            'document_number' => $documentNumber,
            'installments' => [
                ['due_date' => now()->addDays(15)->toDateString(), 'amount' => '100.00'],
                ['due_date' => now()->addDays(30)->toDateString(), 'amount' => '200.00'],
            ],
        ], '300.00'))->assertCreated();

        /** @var SupplierInvoice $invoice */
        $invoice = SupplierInvoice::findOrFail($res->json('data.id'));
        $installments = $invoice->installments()->get();

        return ['invoice' => $invoice, 'first' => $installments[0], 'second' => $installments[1]];
    }

    /**
     * @param  list<array{installment_id: string, amount: string}>  $allocations
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function paymentPayload(array $allocations, array $overrides = []): array
    {
        return array_merge([
            'provider_id' => $this->provider->id,
            'payment_date' => now()->toDateString(),
            'currency_id' => $this->pen->id,
            'payment_method_id' => $this->transferMethod->id,
            'reference' => 'OP-123456',
            'allocations' => $allocations,
        ], $overrides);
    }

    public function test_partial_and_final_payments_update_installments_and_invoice(): void
    {
        ['invoice' => $invoice, 'first' => $first, 'second' => $second] = $this->invoiceWithTwoInstallments();

        $this->actAs(['supplier_payments.create']);

        $res = $this->postJson(self::BASE, $this->paymentPayload([
            ['installment_id' => $first->id, 'amount' => '100.00'],
            ['installment_id' => $second->id, 'amount' => '50.00'],
        ]))->assertCreated();

        $this->assertSame('PP-000001', $res->json('data.number'));
        $this->assertEquals('150.00', $res->json('data.amount'));
        $this->assertSame($this->bankAccount->id, $res->json('data.bank_account_id'));
        $this->assertCount(2, $res->json('data.allocations'));

        $this->assertSame('paid', $first->fresh()?->status->value);
        $this->assertSame('partially_paid', $second->fresh()?->status->value);
        $this->assertEquals('150.00', $second->fresh()?->balance);

        $invoice->refresh();
        $this->assertSame('partially_paid', $invoice->status->value);
        $this->assertEquals('150.00', $invoice->paid_amount);
        $this->assertEquals('150.00', $invoice->balance);

        $this->postJson(self::BASE, $this->paymentPayload([
            ['installment_id' => $second->id, 'amount' => '150.00'],
        ]))->assertCreated();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status->value);
        $this->assertEquals('0.00', $invoice->balance);
        $this->assertSame('paid', $second->fresh()?->status->value);
    }

    public function test_bank_method_registers_movement_in_method_account(): void
    {
        ['first' => $first] = $this->invoiceWithTwoInstallments();

        $this->actAs(['supplier_payments.create']);
        $res = $this->postJson(self::BASE, $this->paymentPayload([
            ['installment_id' => $first->id, 'amount' => '100.00'],
        ]))->assertCreated();

        /** @var BankMovement $movement */
        $movement = BankMovement::where('source_id', $res->json('data.id'))->firstOrFail();
        $this->assertSame($this->bankAccount->id, $movement->bank_account_id);
        $this->assertEquals('100.00', $movement->amount);
        $this->assertSame('PP-000001', $movement->source_number);
        $this->assertEquals('9900.00', $this->bankAccount->fresh()?->balance);
        $this->assertSame(0, CashMovement::count());
    }

    public function test_amount_exceeding_installment_balance_is_rejected(): void
    {
        ['first' => $first] = $this->invoiceWithTwoInstallments();

        $this->actAs(['supplier_payments.create']);
        $this->postJson(self::BASE, $this->paymentPayload([
            ['installment_id' => $first->id, 'amount' => '100.01'],
        ]))->assertStatus(422)->assertJsonValidationErrors(['allocations.0.amount']);

        $this->assertSame(0, BankMovement::count());
    }

    public function test_payment_currency_must_match_installments(): void
    {
        ['first' => $first] = $this->invoiceWithTwoInstallments();

        $this->actAs(['supplier_payments.create']);
        $this->postJson(self::BASE, $this->paymentPayload(
            [['installment_id' => $first->id, 'amount' => '50.00']],
            ['currency_id' => $this->usd->id],
        ))->assertStatus(422)->assertJsonValidationErrors(['allocations.0.installment_id']);
    }

    public function test_installments_of_another_provider_are_rejected(): void
    {
        ['first' => $first] = $this->invoiceWithTwoInstallments();
        $other = Provider::factory()->create();

        $this->actAs(['supplier_payments.create']);
        $this->postJson(self::BASE, $this->paymentPayload(
            [['installment_id' => $first->id, 'amount' => '50.00']],
            ['provider_id' => $other->id],
        ))->assertStatus(422)->assertJsonValidationErrors(['allocations.0.installment_id']);
    }

    public function test_cash_method_requires_open_session(): void
    {
        ['first' => $first] = $this->invoiceWithTwoInstallments();

        $this->actAsCashier(['supplier_payments.create']);
        $this->postJson(self::BASE, $this->paymentPayload(
            [['installment_id' => $first->id, 'amount' => '50.00']],
            ['payment_method_id' => $this->cashMethod->id, 'reference' => null],
        ))->assertStatus(422)->assertJsonValidationErrors(['cash_session_id']);

        $this->assertEquals('100.00', $first->fresh()?->balance);
    }

    public function test_cancel_reverses_cash_movement_and_restores_balances(): void
    {
        ['invoice' => $invoice, 'first' => $first] = $this->invoiceWithTwoInstallments();

        $this->actAsCashier(['supplier_payments.create', 'supplier_payments.cancel']);
        $res = $this->postJson(self::BASE, $this->paymentPayload(
            [['installment_id' => $first->id, 'amount' => '60.00']],
            ['payment_method_id' => $this->cashMethod->id, 'cash_session_id' => $this->session->id, 'reference' => null],
        ))->assertCreated();
        $paymentId = (string) $res->json('data.id');

        $this->assertSame(1, CashMovement::where('source_id', $paymentId)->count());

        $this->postJson(self::BASE."/{$paymentId}/cancel", ['reason' => 'Pago duplicado'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(1, CashMovement::where('source_id', $paymentId)->whereNotNull('reverses_id')->count());
        $this->assertEquals('100.00', $first->fresh()?->balance);
        $this->assertSame('pending', $first->fresh()?->status->value);

        $invoice->refresh();
        $this->assertSame('pending', $invoice->status->value);
        $this->assertEquals('300.00', $invoice->balance);

        $this->postJson(self::BASE."/{$paymentId}/cancel", ['reason' => 'Otra vez'])->assertStatus(422);
    }

    public function test_cancel_reverses_bank_movement(): void
    {
        ['first' => $first] = $this->invoiceWithTwoInstallments();

        $this->actAs(['supplier_payments.create', 'supplier_payments.cancel']);
        $res = $this->postJson(self::BASE, $this->paymentPayload([
            ['installment_id' => $first->id, 'amount' => '100.00'],
        ]))->assertCreated();

        $this->postJson(self::BASE."/{$res->json('data.id')}/cancel", ['reason' => 'Error'])->assertOk();

        $this->assertEquals('10000.00', $this->bankAccount->fresh()?->balance);
        $this->assertSame(2, BankMovement::where('source_id', $res->json('data.id'))->count());
    }

    public function test_cancel_with_closed_cash_session_is_rejected(): void
    {
        ['first' => $first] = $this->invoiceWithTwoInstallments();

        $this->actAsCashier(['supplier_payments.create', 'supplier_payments.cancel']);
        $res = $this->postJson(self::BASE, $this->paymentPayload(
            [['installment_id' => $first->id, 'amount' => '40.00']],
            ['payment_method_id' => $this->cashMethod->id, 'cash_session_id' => $this->session->id, 'reference' => null],
        ))->assertCreated();

        $this->session->update(['status' => CashSessionStatus::Closed->value, 'closed_at' => now()]);

        $this->postJson(self::BASE."/{$res->json('data.id')}/cancel", ['reason' => 'Error'])->assertStatus(422);

        $this->assertEquals('60.00', $first->fresh()?->balance);
    }

    public function test_open_installments_are_listed_by_due_date(): void
    {
        $this->invoiceWithTwoInstallments('000201');
        $this->storeInvoice($this->invoicePayload([
            'document_number' => '000202',
            'due_date' => now()->addDays(5)->toDateString(),
        ], '80.00'))->assertCreated();

        $this->actAs(['supplier_payments.create']);
        $res = $this->getJson(self::BASE.'/open-installments?'.http_build_query([
            'provider_id' => $this->provider->id,
            'currency_id' => $this->pen->id,
        ]))->assertOk();

        $this->assertCount(3, $res->json('data'));
        $this->assertEquals('80.00', $res->json('data.0.balance'));
        $this->assertEquals('100.00', $res->json('data.1.balance'));
        $this->assertEquals('200.00', $res->json('data.2.balance'));
    }

    public function test_full_cycle_order_receipt_invoice_and_payments_balance_out(): void
    {
        ['receipt_id' => $receiptId] = $this->receivedOrder();

        $this->actAs(['supplier_invoices.create']);
        $lines = $this->getJson('/api/v1/purchases/supplier-invoices/prefill?'.http_build_query(['receipt_ids' => [$receiptId]]))
            ->assertOk()
            ->json('data.items');

        $invoice = $this->storeInvoice($this->invoicePayload([
            'prices_include_tax' => false,
            'receipt_ids' => [$receiptId],
            'items' => array_map(fn (array $l) => [
                'description' => $l['description'],
                'item_presentation_id' => $l['item_presentation_id'],
                'purchase_receipt_item_id' => $l['purchase_receipt_item_id'],
                'quantity' => $l['quantity'],
                'unit_price' => $l['unit_price'],
                'tax_type' => $l['tax_type'],
            ], (array) $lines),
        ]))->assertCreated();

        $installmentId = (string) $invoice->json('data.installments.0.id');

        $this->actAs(['supplier_payments.create']);
        $this->postJson(self::BASE, $this->paymentPayload([['installment_id' => $installmentId, 'amount' => '326.00']]))->assertCreated();
        $this->postJson(self::BASE, $this->paymentPayload([['installment_id' => $installmentId, 'amount' => '500.00']]))->assertCreated();

        /** @var SupplierInvoice $fresh */
        $fresh = SupplierInvoice::findOrFail($invoice->json('data.id'));
        $this->assertSame('paid', $fresh->status->value);
        $this->assertEquals('826.00', $fresh->paid_amount);
        $this->assertEquals('0.00', $fresh->balance);
        $this->assertEquals('826.00', (string) BankMovement::where('direction', 'out')->sum('amount'));
        $this->assertEquals('9174.00', $this->bankAccount->fresh()?->balance);
    }

    public function test_index_and_show(): void
    {
        ['first' => $first] = $this->invoiceWithTwoInstallments();

        $this->actAs(['supplier_payments.create']);
        $res = $this->postJson(self::BASE, $this->paymentPayload([['installment_id' => $first->id, 'amount' => '10.00']]))->assertCreated();

        $this->actAs(['supplier_payments.view']);
        $this->getJson(self::BASE)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::BASE."/{$res->json('data.id')}")
            ->assertOk()
            ->assertJsonPath('data.payment_method.settlement', 'bank_account');
    }

    public function test_actions_require_permissions(): void
    {
        ['first' => $first] = $this->invoiceWithTwoInstallments();

        $this->actAs(['supplier_payments.create']);
        $res = $this->postJson(self::BASE, $this->paymentPayload([['installment_id' => $first->id, 'amount' => '10.00']]))->assertCreated();
        $id = (string) $res->json('data.id');

        $this->actAs([]);
        $this->getJson(self::BASE)->assertForbidden();
        $this->getJson(self::BASE."/{$id}")->assertForbidden();
        $this->getJson(self::BASE.'/open-installments?currency_id='.$this->pen->id)->assertForbidden();
        $this->postJson(self::BASE, $this->paymentPayload([['installment_id' => $first->id, 'amount' => '10.00']]))->assertForbidden();
        $this->postJson(self::BASE."/{$id}/cancel", ['reason' => 'Sin permiso'])->assertForbidden();
    }
}
