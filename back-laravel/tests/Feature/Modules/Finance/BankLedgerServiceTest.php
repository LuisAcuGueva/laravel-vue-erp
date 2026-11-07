<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Data\BankMovementData;
use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Exceptions\BankLedgerException;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Services\BankLedgerService;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class BankLedgerServiceTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private BankLedgerService $ledgerService;

    private BankAccount $account;

    private BankPeriod $period;

    private FinanceConcept $incomeConcept;

    private FinanceConcept $expenseConcept;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
        (new SystemParameterSeeder)->run();
        $this->seedUbigeoFixture();

        $this->ledgerService = app(BankLedgerService::class);
        $this->user = User::factory()->create();

        $bank = Bank::first() ?? Bank::factory()->create();
        $currency = Currency::where('is_base', true)->first() ?? Currency::first();

        $this->account = BankAccount::create([
            'bank_id' => $bank->id,
            'currency_id' => $currency->id,
            'alias' => 'BCP Operaciones',
            'account_number' => '191-12345678-0-01',
            'account_type' => BankAccountType::Checking,
            'balance' => '1000.00',
            'is_active' => true,
        ]);

        $this->period = BankPeriod::create([
            'bank_account_id' => $this->account->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => now()->startOfMonth()->toDateString(),
            'opening_balance' => '1000.00',
        ]);

        $this->incomeConcept = FinanceConcept::create([
            'code' => 'TEST_INC',
            'name' => 'Ingreso de prueba',
            'direction' => ConceptDirection::Income,
            'is_active' => true,
            'is_system' => false,
        ]);

        $this->expenseConcept = FinanceConcept::create([
            'code' => 'TEST_EXP',
            'name' => 'Gasto de prueba',
            'direction' => ConceptDirection::Expense,
            'is_active' => true,
            'is_system' => false,
        ]);
    }

    public function test_can_register_income_movement_and_update_balance(): void
    {
        $movement = DB::transaction(function () {
            return $this->ledgerService->register(new BankMovementData(
                bankAccountId: $this->account->id,
                direction: MoneyDirection::In,
                conceptId: $this->incomeConcept->id,
                amount: 250.00,
                operationNumber: 'OP-001',
                description: 'Abono de prueba',
                createdBy: $this->user->id,
            ));
        });

        $this->assertEquals('1250.00', $movement->balance_after);
        $this->assertEquals(250.00, (float) $movement->amount);
        $this->assertEquals('1250.00', $this->account->fresh()->balance);
    }

    public function test_can_register_expense_movement_and_update_balance(): void
    {
        $movement = DB::transaction(function () {
            return $this->ledgerService->register(new BankMovementData(
                bankAccountId: $this->account->id,
                direction: MoneyDirection::Out,
                conceptId: $this->expenseConcept->id,
                amount: 400.00,
                operationNumber: 'OP-002',
                description: 'Cargo de prueba',
                createdBy: $this->user->id,
            ));
        });

        $this->assertEquals('600.00', $movement->balance_after);
        $this->assertEquals('600.00', $this->account->fresh()->balance);
    }

    public function test_throws_exception_if_no_active_database_transaction(): void
    {
        DB::rollBack();

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('BankLedgerService requiere una transacción de base de datos activa.');

            $this->ledgerService->register(new BankMovementData(
                bankAccountId: $this->account->id,
                direction: MoneyDirection::In,
                conceptId: $this->incomeConcept->id,
                amount: 100.00,
            ));
        } finally {
            DB::beginTransaction();
        }
    }

    public function test_throws_exception_if_account_has_no_open_period(): void
    {
        $this->period->update(['status' => BankPeriodStatus::Closed]);

        $this->expectException(BankLedgerException::class);
        $this->expectExceptionCode(409);

        DB::transaction(function () {
            $this->ledgerService->register(new BankMovementData(
                bankAccountId: $this->account->id,
                direction: MoneyDirection::In,
                conceptId: $this->incomeConcept->id,
                amount: 100.00,
            ));
        });
    }

    public function test_cannot_overdraw_balance_when_negative_balance_is_not_allowed(): void
    {
        $this->expectException(BankLedgerException::class);
        $this->expectExceptionCode(422);

        DB::transaction(function () {
            $this->ledgerService->register(new BankMovementData(
                bankAccountId: $this->account->id,
                direction: MoneyDirection::Out,
                conceptId: $this->expenseConcept->id,
                amount: 1500.00,
            ));
        });
    }

    public function test_can_overdraw_balance_when_parameter_allows_negative_balance(): void
    {
        SystemParameter::where('group_key', 'finance')
            ->where('item_key', 'allow_negative_bank_balance')
            ->update(['value' => true]);
        Cache::forget(SystemParameterService::CACHE_KEY);

        $movement = DB::transaction(function () {
            return $this->ledgerService->register(new BankMovementData(
                bankAccountId: $this->account->id,
                direction: MoneyDirection::Out,
                conceptId: $this->expenseConcept->id,
                amount: 1500.00,
            ));
        });

        $this->assertEquals('-500.00', $movement->balance_after);
        $this->assertEquals('-500.00', $this->account->fresh()->balance);
    }

    public function test_can_reverse_movement_in_open_period(): void
    {
        $movement = DB::transaction(function () {
            return $this->ledgerService->register(new BankMovementData(
                bankAccountId: $this->account->id,
                direction: MoneyDirection::Out,
                conceptId: $this->expenseConcept->id,
                amount: 200.00,
                operationNumber: 'OP-REV-1',
                createdBy: $this->user->id,
            ));
        });

        $this->assertEquals('800.00', $this->account->fresh()->balance);

        $reversal = DB::transaction(function () use ($movement) {
            return $this->ledgerService->reverse(
                movement: $movement,
                source: $movement,
                sourceNumber: 'OP-REV-1',
                reason: 'Error de tipeo',
                createdBy: $this->user->id,
            );
        });

        $this->assertEquals('1000.00', $reversal->balance_after);
        $this->assertEquals('1000.00', $this->account->fresh()->balance);
        $this->assertEquals(MoneyDirection::In, $reversal->direction);
        $this->assertEquals($movement->id, $reversal->reverses_id);
    }

    public function test_cannot_reverse_same_movement_twice(): void
    {
        $movement = DB::transaction(function () {
            return $this->ledgerService->register(new BankMovementData(
                bankAccountId: $this->account->id,
                direction: MoneyDirection::Out,
                conceptId: $this->expenseConcept->id,
                amount: 200.00,
            ));
        });

        DB::transaction(function () use ($movement) {
            $this->ledgerService->reverse($movement, $movement, 'OP-REV-2', 'Error');
        });

        $this->expectException(BankLedgerException::class);
        $this->expectExceptionCode(422);

        DB::transaction(function () use ($movement) {
            $this->ledgerService->reverse($movement, $movement, 'OP-REV-2', 'Error repetido');
        });
    }

    public function test_throws_exception_if_movement_date_is_before_period_opening(): void
    {
        // Simula el periodo siguiente a un cierre: abre "mañana" (BankPeriodService::close()).
        $this->period->update(['opened_at' => now()->addDay()->toDateString()]);

        $this->expectException(BankLedgerException::class);
        $this->expectExceptionCode(422);

        DB::transaction(function () {
            $this->ledgerService->register(new BankMovementData(
                bankAccountId: $this->account->id,
                direction: MoneyDirection::In,
                conceptId: $this->incomeConcept->id,
                amount: 100.00,
                movementDate: now(),
            ));
        });

        $this->assertEquals('1000.00', $this->account->fresh()->balance);
    }

    public function test_can_register_movement_dated_exactly_on_period_opening_date(): void
    {
        $this->period->update(['opened_at' => now()->toDateString()]);

        $movement = DB::transaction(function () {
            return $this->ledgerService->register(new BankMovementData(
                bankAccountId: $this->account->id,
                direction: MoneyDirection::In,
                conceptId: $this->incomeConcept->id,
                amount: 100.00,
                movementDate: now(),
            ));
        });

        $this->assertEquals('1100.00', $movement->balance_after);
    }

    public function test_cannot_reverse_movement_if_period_is_closed(): void
    {
        $movement = DB::transaction(function () {
            return $this->ledgerService->register(new BankMovementData(
                bankAccountId: $this->account->id,
                direction: MoneyDirection::In,
                conceptId: $this->incomeConcept->id,
                amount: 200.00,
            ));
        });

        $this->period->update(['status' => BankPeriodStatus::Closed]);

        $this->expectException(BankLedgerException::class);
        $this->expectExceptionCode(422);

        DB::transaction(function () use ($movement) {
            $this->ledgerService->reverse($movement, $movement, 'OP-REV-3', 'Reversión tardía');
        });
    }
}
