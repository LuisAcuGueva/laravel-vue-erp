<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class BankMovementsApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private User $admin;

    private Branch $branch;

    private BankAccount $account;

    private BankPeriod $period;

    private FinanceConcept $manualConcept;

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
            'alias' => 'BCP Operaciones',
            'account_number' => '191-11223344-0-01',
            'account_type' => BankAccountType::Checking,
            'balance' => '1000.00',
            'is_active' => true,
        ]);

        $this->period = BankPeriod::create([
            'bank_account_id' => $this->account->id,
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => '2026-10-01',
            'opening_balance' => '1000.00',
        ]);

        $this->manualConcept = FinanceConcept::create([
            'code' => 'COM_MANUAL',
            'name' => 'Comisión de prueba',
            'direction' => ConceptDirection::Expense,
            'is_active' => true,
            'is_system' => false,
        ]);
    }

    public function test_can_register_manual_movement_with_voucher(): void
    {
        Storage::fake('local');
        $voucher = UploadedFile::fake()->create('voucher.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->post("/api/v1/finance/bank-accounts/{$this->account->id}/movements", [
                'direction' => 'out',
                'concept_id' => $this->manualConcept->id,
                'amount' => '45.00',
                'operation_number' => 'COM-101',
                'movement_date' => '2026-10-15',
                'description' => 'Comisión por mantenimiento',
                'voucher' => $voucher,
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.amount', 45)
            ->assertJsonPath('data.balance_after', 955)
            ->assertJsonPath('data.is_manual', true);

        $this->assertEquals('955.00', $this->account->fresh()->balance);

        /** @var BankMovement $movement */
        $movement = BankMovement::first();
        $this->assertNotNull($movement->voucher_path);
        Storage::disk('local')->assertExists($movement->voucher_path);
    }

    public function test_cannot_register_manual_movement_with_system_concept(): void
    {
        $systemConcept = FinanceConcept::where('code', SystemConcept::BankDeposit->value)->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson("/api/v1/finance/bank-accounts/{$this->account->id}/movements", [
                'direction' => 'in',
                'concept_id' => $systemConcept->id,
                'amount' => '100.00',
                'movement_date' => '2026-10-15',
                'description' => 'Depósito directo no permitido por esta vía',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['concept_id']);
    }

    public function test_can_download_voucher_authenticated(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->putFile('bank-vouchers', UploadedFile::fake()->create('voucher.pdf', 100, 'application/pdf'));

        $movement = BankMovement::create([
            'bank_account_id' => $this->account->id,
            'bank_period_id' => $this->period->id,
            'branch_id' => $this->branch->id,
            'direction' => MoneyDirection::Out,
            'concept_id' => $this->manualConcept->id,
            'amount' => '50.00',
            'balance_after' => '950.00',
            'movement_date' => '2026-10-15',
            'description' => 'Prueba de descarga',
            'voucher_path' => $path,
            'is_manual' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->get("/api/v1/finance/bank-movements/{$movement->id}/voucher");

        $response->assertOk();
    }

    public function test_can_cancel_manual_movement_in_open_period(): void
    {
        $movement = BankMovement::create([
            'bank_account_id' => $this->account->id,
            'bank_period_id' => $this->period->id,
            'branch_id' => $this->branch->id,
            'direction' => MoneyDirection::Out,
            'concept_id' => $this->manualConcept->id,
            'amount' => '100.00',
            'balance_after' => '900.00',
            'movement_date' => '2026-10-15',
            'description' => 'Cargo erróneo',
            'is_manual' => true,
        ]);
        $this->account->update(['balance' => '900.00']);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson("/api/v1/finance/bank-movements/{$movement->id}/cancel", [
                'reason' => 'Cargo duplicado por el banco',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.balance_after', 1000);

        $this->assertEquals('1000.00', $this->account->fresh()->balance);
    }

    public function test_cannot_cancel_manual_movement_in_closed_period(): void
    {
        $movement = BankMovement::create([
            'bank_account_id' => $this->account->id,
            'bank_period_id' => $this->period->id,
            'branch_id' => $this->branch->id,
            'direction' => MoneyDirection::Out,
            'concept_id' => $this->manualConcept->id,
            'amount' => '100.00',
            'balance_after' => '900.00',
            'movement_date' => '2026-10-15',
            'description' => 'Cargo en periodo cerrado',
            'is_manual' => true,
        ]);

        $this->period->update(['status' => BankPeriodStatus::Closed]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->postJson("/api/v1/finance/bank-movements/{$movement->id}/cancel", [
                'reason' => 'Anulación tardía',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank']);
    }

    public function test_list_movements_with_filters_and_opening_closing(): void
    {
        BankMovement::create([
            'bank_account_id' => $this->account->id,
            'bank_period_id' => $this->period->id,
            'direction' => MoneyDirection::In,
            'concept_id' => FinanceConcept::where('direction', ConceptDirection::Income)->first()->id,
            'amount' => '500.00',
            'balance_after' => '1500.00',
            'movement_date' => '2026-10-05',
            'description' => 'Abono 1',
        ]);

        BankMovement::create([
            'bank_account_id' => $this->account->id,
            'bank_period_id' => $this->period->id,
            'direction' => MoneyDirection::Out,
            'concept_id' => $this->manualConcept->id,
            'amount' => '200.00',
            'balance_after' => '1300.00',
            'movement_date' => '2026-10-10',
            'description' => 'Cargo 1',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Branch-Id', $this->branch->id)
            ->getJson("/api/v1/finance/bank-accounts/{$this->account->id}/movements?date_from=2026-10-01&date_to=2026-10-31");

        $response->assertOk()
            ->assertJsonPath('data.opening', 1000)
            ->assertJsonPath('data.closing', 1300)
            ->assertJsonCount(2, 'data.movements')
            ->assertJsonPath('data.movements.0.description', 'Cargo 1')
            ->assertJsonPath('data.movements.1.description', 'Abono 1');
    }
}
