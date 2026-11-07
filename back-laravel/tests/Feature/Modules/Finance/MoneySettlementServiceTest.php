<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Finance\Data\SettlementData;
use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Services\MoneySettlementService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class MoneySettlementServiceTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private MoneySettlementService $service;

    private Branch $branch;

    private Box $box;

    private CashSession $session;

    private User $cashier;

    private Currency $base;

    private Currency $usd;

    private PaymentMethod $cashMethod;

    private BankAccount $account;

    private PaymentMethod $transferMethod;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
        (new SystemParameterSeeder)->run();
        $this->seedUbigeoFixture();

        $this->service = app(MoneySettlementService::class);

        $this->branch = Branch::create([
            'code' => 'B01',
            'name' => 'Sede Central',
            'ubigeo' => '150101',
            'address' => 'Av. Central 123',
            'is_main' => true,
            'is_active' => true,
        ]);

        $userRole = Role::where('name', 'user')->firstOrFail();
        $this->box = Box::create([
            'branch_id' => $this->branch->id,
            'code' => 'CJ01',
            'name' => 'Caja 1',
            'is_active' => true,
        ]);
        $this->box->roles()->sync([$userRole->id]);

        /** @var User $cashier */
        $cashier = User::factory()->create();
        $cashier->assignRole($userRole);
        $this->cashier = $cashier;

        $this->session = CashSession::create([
            'number' => 'CJ-000001',
            'box_id' => $this->box->id,
            'branch_id' => $this->branch->id,
            'status' => CashSessionStatus::Open->value,
            'opened_by' => $cashier->id,
            'opened_at' => now(),
            'opening_amount' => 500.00,
        ]);

        $this->base = Currency::base() ?? Currency::where('is_base', true)->firstOrFail();
        $this->usd = Currency::where('code', 'USD')->firstOrFail();
        $this->cashMethod = PaymentMethod::where('code', 'CASH')->firstOrFail();

        $this->account = BankAccount::create([
            'bank_id' => (Bank::first() ?? Bank::factory()->create())->id,
            'currency_id' => $this->base->id,
            'alias' => 'BCP Operaciones',
            'account_number' => '191-12345678-0-01',
            'account_type' => BankAccountType::Checking,
            'balance' => '1000.00',
            'is_active' => true,
        ]);

        BankPeriod::create([
            'bank_account_id' => $this->account->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => now()->startOfMonth()->toDateString(),
            'opening_balance' => '1000.00',
        ]);

        $this->transferMethod = PaymentMethod::create([
            'code' => 'TRF_BCP',
            'name' => 'Transferencia BCP',
            'kind' => PaymentMethodKind::Transfer,
            'settlement' => Settlement::BankAccount,
            'bank_account_id' => $this->account->id,
            'requires_reference' => true,
            'is_active' => true,
        ]);
    }

    private function data(array $overrides = []): SettlementData
    {
        $args = array_merge([
            'paymentMethodId' => $this->cashMethod->id,
            'direction' => MoneyDirection::Out,
            'amount' => '120.00',
            'currencyId' => $this->base->id,
            'source' => $this->session,
            'sourceNumber' => 'PP-000001',
            'branchId' => $this->branch->id,
            'cashSessionId' => $this->session->id,
            'concept' => SystemConcept::SupplierPayment,
            'reference' => null,
            'description' => 'Pago a proveedor',
            'user' => $this->cashier,
        ], $overrides);

        return new SettlementData(...$args);
    }

    public function test_cash_method_registers_cash_movement_in_session(): void
    {
        $movement = DB::transaction(fn () => $this->service->settle($this->data()));

        $this->assertInstanceOf(CashMovement::class, $movement);
        $this->assertSame($this->session->id, $movement->cash_session_id);
        $this->assertSame(MoneyDirection::Out->value, $movement->direction instanceof MoneyDirection ? $movement->direction->value : $movement->direction);
        $this->assertTrue((bool) $movement->affects_cash);
        $this->assertEquals('120.00', $movement->amount);
        $this->assertSame(SystemConcept::SupplierPayment->model()?->id, $movement->concept_id);
        $this->assertSame('PP-000001', $movement->source_number);
        $this->assertSame(0, BankMovement::count());
    }

    public function test_cash_method_requires_session(): void
    {
        $this->expectException(FinanceRuleException::class);

        DB::transaction(fn () => $this->service->settle($this->data(['cashSessionId' => null])));
    }

    public function test_cash_method_rejects_closed_session(): void
    {
        $this->session->update(['status' => CashSessionStatus::Closed->value]);

        $this->expectException(FinanceRuleException::class);

        DB::transaction(fn () => $this->service->settle($this->data()));
    }

    public function test_cash_method_requires_base_currency(): void
    {
        $this->expectException(FinanceRuleException::class);
        $this->expectExceptionMessage('moneda base');

        DB::transaction(fn () => $this->service->settle($this->data(['currencyId' => $this->usd->id])));
    }

    public function test_cash_method_rejects_session_of_another_branch(): void
    {
        $other = Branch::create([
            'code' => 'B02',
            'name' => 'Sede Norte',
            'ubigeo' => '150101',
            'address' => 'Av. Norte 456',
            'is_main' => false,
            'is_active' => true,
        ]);

        $this->expectException(FinanceRuleException::class);

        DB::transaction(fn () => $this->service->settle($this->data(['branchId' => $other->id])));
    }

    public function test_cash_method_rejects_user_without_box_access(): void
    {
        /** @var User $stranger */
        $stranger = User::factory()->create();

        $this->expectException(AccessDeniedHttpException::class);

        DB::transaction(fn () => $this->service->settle($this->data(['user' => $stranger])));
    }

    public function test_bank_method_registers_movement_in_method_account(): void
    {
        $movement = DB::transaction(fn () => $this->service->settle($this->data([
            'paymentMethodId' => $this->transferMethod->id,
            'cashSessionId' => null,
            'reference' => 'OP-778899',
        ])));

        $this->assertInstanceOf(BankMovement::class, $movement);
        $this->assertSame($this->account->id, $movement->bank_account_id);
        $this->assertSame('OP-778899', $movement->operation_number);
        $this->assertEquals('880.00', $this->account->fresh()?->balance);
        $this->assertSame(0, CashMovement::count());
    }

    public function test_bank_method_requires_reference_when_method_demands_it(): void
    {
        $this->expectException(FinanceRuleException::class);
        $this->expectExceptionMessage('referencia');

        DB::transaction(fn () => $this->service->settle($this->data([
            'paymentMethodId' => $this->transferMethod->id,
            'cashSessionId' => null,
        ])));
    }

    public function test_bank_method_rejects_currency_different_from_account(): void
    {
        $this->expectException(FinanceRuleException::class);

        DB::transaction(fn () => $this->service->settle($this->data([
            'paymentMethodId' => $this->transferMethod->id,
            'cashSessionId' => null,
            'reference' => 'OP-1',
            'currencyId' => $this->usd->id,
        ])));
    }

    public function test_bank_method_without_account_is_rejected(): void
    {
        $card = PaymentMethod::create([
            'code' => 'VISA',
            'name' => 'Tarjeta Visa',
            'kind' => PaymentMethodKind::Card,
            'settlement' => Settlement::BankAccount,
            'requires_reference' => false,
            'is_active' => true,
        ]);

        $this->expectException(FinanceRuleException::class);

        DB::transaction(fn () => $this->service->settle($this->data([
            'paymentMethodId' => $card->id,
            'cashSessionId' => null,
        ])));
    }

    public function test_inactive_method_is_rejected(): void
    {
        $this->transferMethod->update(['is_active' => false]);

        $this->expectException(FinanceRuleException::class);

        DB::transaction(fn () => $this->service->settle($this->data([
            'paymentMethodId' => $this->transferMethod->id,
            'reference' => 'OP-1',
        ])));
    }

    public function test_unsettle_reverses_cash_and_bank_movements_once(): void
    {
        DB::transaction(function () {
            $this->service->settle($this->data());
            $this->service->settle($this->data([
                'paymentMethodId' => $this->transferMethod->id,
                'cashSessionId' => null,
                'reference' => 'OP-1',
                'amount' => '50.00',
            ]));
        });

        $reversed = DB::transaction(fn () => $this->service->unsettle($this->session, 'PP-000001', 'Error de registro', $this->cashier->id));

        $this->assertSame(2, $reversed);
        $this->assertSame(1, CashMovement::whereNotNull('reverses_id')->count());
        $this->assertSame(1, BankMovement::whereNotNull('reverses_id')->count());
        $this->assertEquals('1000.00', $this->account->fresh()?->balance);

        $again = DB::transaction(fn () => $this->service->unsettle($this->session, 'PP-000001', 'Repetido'));

        $this->assertSame(0, $again);
    }

    public function test_settle_requires_active_transaction(): void
    {
        DB::rollBack();

        try {
            $this->expectException(LogicException::class);
            $this->service->settle($this->data());
        } finally {
            DB::beginTransaction();
        }
    }
}
