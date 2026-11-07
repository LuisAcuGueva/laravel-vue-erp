<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Models\Currency;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class BankPeriodsApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private User $admin;

    private Branch $branch;

    private BankAccount $account;

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

        $bank = Bank::first() ?? Bank::factory()->create();
        $currency = Currency::where('is_base', true)->first() ?? Currency::first();

        $this->account = BankAccount::create([
            'bank_id' => $bank->id,
            'currency_id' => $currency->id,
            'branch_id' => $this->branch->id,
            'alias' => 'Cuenta Corriente Soles',
            'account_number' => '191-99887766-0-01',
            'account_type' => BankAccountType::Checking,
            'balance' => '0.00',
            'is_active' => true,
        ]);
    }

    public function test_can_open_initial_period_when_account_has_no_periods(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson("/api/v1/finance/bank-accounts/{$this->account->id}/periods", [
                'opening_balance' => 5000.00,
                'opened_at' => '2026-10-01',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.number', 1)
            ->assertJsonPath('data.status', BankPeriodStatus::Open->value)
            ->assertJsonPath('data.opening_balance', 5000);

        $this->assertEquals('5000.00', $this->account->fresh()->balance);
    }

    public function test_cannot_open_initial_period_if_account_already_has_periods(): void
    {
        BankPeriod::create([
            'bank_account_id' => $this->account->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => '2026-10-01',
            'opening_balance' => '1000.00',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson("/api/v1/finance/bank-accounts/{$this->account->id}/periods", [
                'opening_balance' => 2000.00,
                'opened_at' => '2026-10-02',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank']);
    }

    public function test_can_close_period_without_difference_and_automatically_open_next(): void
    {
        $this->account->update(['balance' => '5000.00']);
        $period = BankPeriod::create([
            'bank_account_id' => $this->account->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => '2026-10-01',
            'opening_balance' => '5000.00',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson("/api/v1/finance/bank-periods/{$period->id}/close", [
                'statement_balance' => 5000.00,
                'closed_at' => '2026-10-31',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.closed.status', BankPeriodStatus::Closed->value)
            ->assertJsonPath('data.closed.difference', 0)
            ->assertJsonPath('data.next.number', 2)
            ->assertJsonPath('data.next.status', BankPeriodStatus::Open->value)
            ->assertJsonPath('data.next.opening_balance', 5000)
            ->assertJsonPath('data.next.opened_at', '2026-11-01');

        $this->assertDatabaseHas('bank_periods', [
            'id' => $period->id,
            'status' => BankPeriodStatus::Closed->value,
            'difference' => '0.00',
        ]);
    }

    public function test_cannot_close_period_with_difference_and_no_notes(): void
    {
        $this->account->update(['balance' => '5000.00']);
        $period = BankPeriod::create([
            'bank_account_id' => $this->account->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => '2026-10-01',
            'opening_balance' => '5000.00',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson("/api/v1/finance/bank-periods/{$period->id}/close", [
                'statement_balance' => 4950.00,
                'closed_at' => '2026-10-31',
                'notes' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank']);
    }

    public function test_can_close_period_with_difference_when_notes_provided(): void
    {
        $this->account->update(['balance' => '5000.00']);
        $period = BankPeriod::create([
            'bank_account_id' => $this->account->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => '2026-10-01',
            'opening_balance' => '5000.00',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson("/api/v1/finance/bank-periods/{$period->id}/close", [
                'statement_balance' => 4950.00,
                'closed_at' => '2026-10-31',
                'notes' => 'Comisión de mantenimiento bancario no registrada aún.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.closed.difference', -50)
            ->assertJsonPath('data.next.number', 2);
    }

    public function test_cannot_close_period_with_date_before_opening_date(): void
    {
        $period = BankPeriod::create([
            'bank_account_id' => $this->account->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => '2026-10-15',
            'opening_balance' => '1000.00',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson("/api/v1/finance/bank-periods/{$period->id}/close", [
                'statement_balance' => 1000.00,
                'closed_at' => '2026-10-10',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank']);
    }

    public function test_list_periods_of_bank_account(): void
    {
        BankPeriod::create([
            'bank_account_id' => $this->account->id,
            'number' => 1,
            'status' => BankPeriodStatus::Closed,
            'opened_at' => '2026-09-01',
            'opening_balance' => '1000.00',
            'closed_at' => '2026-09-30',
            'statement_balance' => '1000.00',
            'expected_balance' => '1000.00',
            'difference' => '0.00',
        ]);

        BankPeriod::create([
            'bank_account_id' => $this->account->id,
            'number' => 2,
            'status' => BankPeriodStatus::Open,
            'opened_at' => '2026-10-01',
            'opening_balance' => '1000.00',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->getJson("/api/v1/finance/bank-accounts/{$this->account->id}/periods");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.number', 2)
            ->assertJsonPath('data.1.number', 1);
    }
}
