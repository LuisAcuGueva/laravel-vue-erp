<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\FinanceConcept;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class VerifyBankBalancesCommandTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private BankAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
        (new SystemParameterSeeder)->run();
        $this->seedUbigeoFixture();

        $bank = Bank::first() ?? Bank::factory()->create();
        $currency = Currency::where('is_base', true)->firstOrFail();

        $this->account = BankAccount::create([
            'bank_id' => $bank->id,
            'currency_id' => $currency->id,
            'account_type' => BankAccountType::Checking,
            'alias' => 'BCP Principal',
            'account_number' => '191-00000000-0-01',
            'balance' => '1200.00',
            'is_active' => true,
        ]);

        $period = BankPeriod::create([
            'bank_account_id' => $this->account->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => '2026-10-01',
            'opening_balance' => '1000.00',
        ]);

        $concept = FinanceConcept::firstOrFail();

        BankMovement::create([
            'bank_account_id' => $this->account->id,
            'bank_period_id' => $period->id,
            'direction' => MoneyDirection::In,
            'concept_id' => $concept->id,
            'amount' => '300.00',
            'balance_after' => '1300.00',
            'movement_date' => '2026-10-05',
        ]);

        BankMovement::create([
            'bank_account_id' => $this->account->id,
            'bank_period_id' => $period->id,
            'direction' => MoneyDirection::Out,
            'concept_id' => $concept->id,
            'amount' => '100.00',
            'balance_after' => '1200.00',
            'movement_date' => '2026-10-10',
        ]);
    }

    public function test_command_passes_when_balances_match_ledger(): void
    {
        $this->artisan('finance:verify-bank-balances')
            ->expectsOutputToContain('Todos los saldos bancarios concuerdan con el ledger.')
            ->assertExitCode(0);
    }

    public function test_command_fails_when_balance_has_discrepancy(): void
    {
        // Alter balance directly to simulate a data corruption or discrepancy
        $this->account->forceFill(['balance' => '9999.00'])->save();

        $this->artisan('finance:verify-bank-balances')
            ->expectsOutputToContain('DISCREPANCIA')
            ->assertExitCode(1);
    }
}
