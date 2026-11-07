<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Purchases;

use App\Modules\Purchases\Models\PayableInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpPayables;
use Tests\TestCase;

class AccountsPayableApiTest extends TestCase
{
    use RefreshDatabase, SetsUpPayables;

    private const BASE = '/api/v1/purchases/accounts-payable';

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
     * Factura de un solo pago emitida y vencida en días relativos a hoy (negativo = pasado).
     */
    private function invoiceDue(string $number, int $issuedDaysAgo, int $dueInDays, string $amount, ?string $currencyId = null): PayableInstallment
    {
        $payload = $this->invoicePayload([
            'document_number' => $number,
            'issue_date' => now()->subDays($issuedDaysAgo)->toDateString(),
            'due_date' => now()->addDays($dueInDays)->toDateString(),
        ], $amount);

        if ($currencyId !== null) {
            $payload['currency_id'] = $currencyId;
            $payload['exchange_rate'] = '3.8000';
        }

        $res = $this->storeInvoice($payload)->assertCreated();

        return PayableInstallment::findOrFail($res->json('data.installments.0.id'));
    }

    /**
     * Cuatro facturas en soles en tramos distintos y una en dólares al corriente.
     */
    private function seedAgingScenario(): void
    {
        $this->invoiceDue('A-1', 5, 5, '50.00');     // corriente
        $this->invoiceDue('B-1', 20, -10, '60.00');  // 1–30
        $this->invoiceDue('C-1', 60, -45, '70.00');  // 31–60
        $this->invoiceDue('D-1', 120, -100, '80.00'); // > 90
        $this->invoiceDue('U-1', 1, 10, '25.00', $this->usd->id);
    }

    public function test_index_lists_open_installments_with_overdue_flag_and_totals_by_currency(): void
    {
        $this->seedAgingScenario();

        $this->actAs(['supplier_invoices.view']);
        $res = $this->getJson(self::BASE)->assertOk();

        $this->assertCount(5, $res->json('data'));
        // Ordenadas por vencimiento: la más antigua primero.
        $this->assertSame('D-1', $res->json('data.0.invoice.document_number'));
        $this->assertTrue($res->json('data.0.is_overdue'));
        $this->assertSame(100, $res->json('data.0.days_overdue'));

        $totals = collect($res->json('meta.totals_by_currency'))->keyBy('code');
        $this->assertEquals('260.00', $totals['PEN']['balance']);
        $this->assertEquals('210.00', $totals['PEN']['overdue']);
        $this->assertEquals('25.00', $totals['USD']['balance']);
        $this->assertEquals('0.00', $totals['USD']['overdue']);

        $overdue = $this->getJson(self::BASE.'?overdue=1')->assertOk();
        $this->assertCount(3, $overdue->json('data'));

        $usd = $this->getJson(self::BASE.'?currency_id='.$this->usd->id)->assertOk();
        $this->assertCount(1, $usd->json('data'));
    }

    public function test_paid_installments_leave_the_listing(): void
    {
        $installment = $this->invoiceDue('P-1', 0, 10, '40.00');

        $this->actAs(['supplier_payments.create']);
        $this->postJson('/api/v1/purchases/supplier-payments', [
            'provider_id' => $this->provider->id,
            'payment_date' => now()->toDateString(),
            'currency_id' => $this->pen->id,
            'payment_method_id' => $this->transferMethod->id,
            'allocations' => [['installment_id' => $installment->id, 'amount' => '40.00']],
        ])->assertCreated();

        $this->actAs(['supplier_invoices.view']);
        $this->getJson(self::BASE)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_aging_groups_balances_by_provider_currency_and_bucket(): void
    {
        $this->seedAgingScenario();

        $this->actAs(['supplier_invoices.view']);
        $res = $this->getJson(self::BASE.'/aging')->assertOk();

        $rows = collect($res->json('data'))->keyBy('currency.code');
        $this->assertCount(2, $rows);

        $pen = $rows['PEN'];
        $this->assertSame($this->provider->id, $pen['provider']['id']);
        $this->assertEquals('50.00', $pen['current']);
        $this->assertEquals('60.00', $pen['d1_30']);
        $this->assertEquals('70.00', $pen['d31_60']);
        $this->assertEquals('0.00', $pen['d61_90']);
        $this->assertEquals('80.00', $pen['over_90']);
        $this->assertEquals('260.00', $pen['total']);

        $this->assertEquals('25.00', $rows['USD']['current']);
        $this->assertEquals('25.00', $rows['USD']['total']);
    }

    public function test_aging_as_of_a_past_date_moves_balances_between_buckets(): void
    {
        $this->invoiceDue('B-1', 20, -10, '60.00');

        $this->actAs(['supplier_invoices.view']);
        $res = $this->getJson(self::BASE.'/aging?as_of='.now()->subDays(15)->toDateString())->assertOk();

        $this->assertEquals('60.00', $res->json('data.0.current'));
        $this->assertEquals('0.00', $res->json('data.0.d1_30'));
    }

    public function test_provider_statement_has_opening_running_and_closing_balance(): void
    {
        $this->seedAgingScenario();
        $installment = PayableInstallment::whereHas('invoice', fn ($q) => $q->where('document_number', 'A-1'))->firstOrFail();

        $this->actAs(['supplier_payments.create']);
        $this->postJson('/api/v1/purchases/supplier-payments', [
            'provider_id' => $this->provider->id,
            'payment_date' => now()->toDateString(),
            'currency_id' => $this->pen->id,
            'payment_method_id' => $this->transferMethod->id,
            'allocations' => [['installment_id' => $installment->id, 'amount' => '30.00']],
        ])->assertCreated();

        $this->actAs(['supplier_invoices.view']);
        $res = $this->getJson("/api/v1/purchases/providers/{$this->provider->id}/statement?".http_build_query([
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to' => now()->toDateString(),
        ]))->assertOk();

        $this->assertSame('PEN', $res->json('data.currency.code'));
        $this->assertEquals('150.00', $res->json('data.opening'));
        $this->assertCount(3, $res->json('data.entries'));
        $this->assertSame('invoice', $res->json('data.entries.0.type'));
        $this->assertEquals('210.00', $res->json('data.entries.0.balance'));
        $this->assertEquals('260.00', $res->json('data.entries.1.balance'));
        $this->assertSame('payment', $res->json('data.entries.2.type'));
        $this->assertEquals('30.00', $res->json('data.entries.2.credit'));
        $this->assertEquals('230.00', $res->json('data.closing'));
    }

    public function test_actions_require_permissions(): void
    {
        $this->actAs([]);
        $this->getJson(self::BASE)->assertForbidden();
        $this->getJson(self::BASE.'/aging')->assertForbidden();
        $this->getJson("/api/v1/purchases/providers/{$this->provider->id}/statement?date_from=2026-01-01&date_to=2026-01-31")->assertForbidden();
    }
}
