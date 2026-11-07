<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Enums\BankOperationStatus;
use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class BankOperationsApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private User $admin;

    private Branch $branch;

    private Box $box;

    private CashSession $session;

    private BankAccount $sourceAccount;

    private BankAccount $targetAccount;

    private BankAccount $usdAccount;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
        (new SystemParameterSeeder)->run();
        $this->seedUbigeoFixture();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->branch = Branch::create([
            'code' => 'B01',
            'name' => 'Sede Principal',
            'ubigeo' => '150101',
            'address' => 'Av. Principal 123',
            'is_main' => true,
            'is_active' => true,
        ]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);

        $pair = app(JwtService::class)->createTokenPair($this->admin);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);
        $this->withHeader('X-Branch-Id', $this->branch->id);

        $this->box = Box::create([
            'branch_id' => $this->branch->id,
            'code' => 'BOX-1',
            'name' => 'Caja Principal',
            'is_active' => true,
        ]);

        $this->session = CashSession::create([
            'number' => 'CJ-000001',
            'branch_id' => $this->branch->id,
            'box_id' => $this->box->id,
            'opened_by' => $this->admin->id,
            'opened_at' => now(),
            'opening_amount' => '1000.00',
            'status' => CashSessionStatus::Open,
        ]);

        $bank = Bank::first() ?? Bank::factory()->create();
        $baseCurrency = Currency::where('is_base', true)->firstOrFail();
        $usdCurrency = Currency::where('code', 'USD')->firstOrFail();

        $this->sourceAccount = BankAccount::create([
            'bank_id' => $bank->id,
            'currency_id' => $baseCurrency->id,
            'branch_id' => $this->branch->id,
            'alias' => 'BCP Soles Origen',
            'account_number' => '191-11111111-0-01',
            'account_type' => BankAccountType::Checking,
            'balance' => '2000.00',
            'is_active' => true,
        ]);

        BankPeriod::create([
            'bank_account_id' => $this->sourceAccount->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => '2026-10-01',
            'opening_balance' => '2000.00',
        ]);

        $this->targetAccount = BankAccount::create([
            'bank_id' => $bank->id,
            'currency_id' => $baseCurrency->id,
            'branch_id' => $this->branch->id,
            'alias' => 'BBVA Soles Destino',
            'account_number' => '0011-22222222-01',
            'account_type' => BankAccountType::Checking,
            'balance' => '500.00',
            'is_active' => true,
        ]);

        BankPeriod::create([
            'bank_account_id' => $this->targetAccount->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => '2026-10-01',
            'opening_balance' => '500.00',
        ]);

        $this->usdAccount = BankAccount::create([
            'bank_id' => $bank->id,
            'currency_id' => $usdCurrency->id,
            'branch_id' => $this->branch->id,
            'alias' => 'BCP Dólares',
            'account_number' => '191-33333333-1-01',
            'account_type' => BankAccountType::Savings,
            'balance' => '100.00',
            'is_active' => true,
        ]);

        BankPeriod::create([
            'bank_account_id' => $this->usdAccount->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => '2026-10-01',
            'opening_balance' => '100.00',
        ]);
    }

    public function test_can_register_deposit_from_cash_to_bank(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson('/api/v1/finance/bank-operations', [
                'type' => 'deposit',
                'bank_account_id' => $this->sourceAccount->id,
                'cash_session_id' => $this->session->id,
                'amount' => 400.00,
                'operation_number' => 'DEP-001',
                'operation_date' => '2026-10-15',
                'notes' => 'Depósito de ventas',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'deposit')
            ->assertJsonPath('data.status', BankOperationStatus::Completed->value)
            ->assertJsonPath('data.amount', 400);

        $this->assertEquals('2400.00', $this->sourceAccount->fresh()->balance);
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $this->session->id,
            'direction' => 'out',
            'amount' => '400.00',
        ]);
    }

    public function test_cannot_deposit_more_than_available_cash_in_session(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson('/api/v1/finance/bank-operations', [
                'type' => 'deposit',
                'bank_account_id' => $this->sourceAccount->id,
                'cash_session_id' => $this->session->id,
                'amount' => 1500.00,
                'operation_number' => 'DEP-002',
                'operation_date' => '2026-10-15',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank']);
    }

    public function test_cannot_deposit_into_foreign_currency_account(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson('/api/v1/finance/bank-operations', [
                'type' => 'deposit',
                'bank_account_id' => $this->usdAccount->id,
                'cash_session_id' => $this->session->id,
                'amount' => 100.00,
                'operation_number' => 'DEP-003',
                'operation_date' => '2026-10-15',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank']);
    }

    public function test_can_register_withdrawal_from_bank_to_cash(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson('/api/v1/finance/bank-operations', [
                'type' => 'withdrawal',
                'bank_account_id' => $this->sourceAccount->id,
                'cash_session_id' => $this->session->id,
                'amount' => 500.00,
                'operation_number' => 'RET-001',
                'operation_date' => '2026-10-15',
                'notes' => 'Retiro para sencillo',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'withdrawal')
            ->assertJsonPath('data.amount', 500);

        $this->assertEquals('1500.00', $this->sourceAccount->fresh()->balance);
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $this->session->id,
            'direction' => 'in',
            'amount' => '500.00',
        ]);
    }

    public function test_can_transfer_between_same_currency_accounts(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson('/api/v1/finance/bank-operations', [
                'type' => 'transfer',
                'bank_account_id' => $this->sourceAccount->id,
                'target_bank_account_id' => $this->targetAccount->id,
                'amount' => 300.00,
                'operation_number' => 'TRF-001',
                'operation_date' => '2026-10-15',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'transfer')
            ->assertJsonPath('data.amount', 300)
            ->assertJsonPath('data.target_amount', 300);

        $this->assertEquals('1700.00', $this->sourceAccount->fresh()->balance);
        $this->assertEquals('800.00', $this->targetAccount->fresh()->balance);
    }

    public function test_can_transfer_between_different_currencies_with_explicit_rate(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson('/api/v1/finance/bank-operations', [
                'type' => 'transfer',
                'bank_account_id' => $this->sourceAccount->id,
                'target_bank_account_id' => $this->usdAccount->id,
                'amount' => 375.00,
                'exchange_rate' => 0.2667,
                'operation_number' => 'TRF-FX-1',
                'operation_date' => '2026-10-15',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'transfer')
            ->assertJsonPath('data.amount', 375)
            ->assertJsonPath('data.target_amount', 100.01);

        $this->assertEquals('1625.00', $this->sourceAccount->fresh()->balance);
        $this->assertEquals('200.01', $this->usdAccount->fresh()->balance);
    }

    public function test_can_cancel_deposit_and_reverse_both_bank_and_cash(): void
    {
        $createRes = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson('/api/v1/finance/bank-operations', [
                'type' => 'deposit',
                'bank_account_id' => $this->sourceAccount->id,
                'cash_session_id' => $this->session->id,
                'amount' => 200.00,
                'operation_number' => 'DEP-CAN-1',
                'operation_date' => '2026-10-15',
            ]);

        $operationId = $createRes->json('data.id');
        $this->assertEquals('2200.00', $this->sourceAccount->fresh()->balance);

        $cancelRes = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson("/api/v1/finance/bank-operations/{$operationId}/cancel", [
                'reason' => 'Voucher con error de cuenta de destino',
            ]);

        $cancelRes->assertOk()
            ->assertJsonPath('data.status', BankOperationStatus::Cancelled->value);

        $this->assertEquals('2000.00', $this->sourceAccount->fresh()->balance);
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $this->session->id,
            'direction' => 'in',
            'amount' => '200.00',
        ]);
    }

    public function test_cannot_cancel_deposit_if_cash_session_was_closed(): void
    {
        $createRes = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson('/api/v1/finance/bank-operations', [
                'type' => 'deposit',
                'bank_account_id' => $this->sourceAccount->id,
                'cash_session_id' => $this->session->id,
                'amount' => 200.00,
                'operation_number' => 'DEP-CAN-2',
                'operation_date' => '2026-10-15',
            ]);

        $createRes->assertCreated();
        $operationId = $createRes->json('data.id');

        $this->session->update(['status' => CashSessionStatus::Closed]);

        $cancelRes = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson("/api/v1/finance/bank-operations/{$operationId}/cancel", [
                'reason' => 'Anulación fallida por sesión cerrada',
            ]);

        $cancelRes->assertStatus(422);

        $this->assertEquals('2200.00', $this->sourceAccount->fresh()->balance);
    }
}
