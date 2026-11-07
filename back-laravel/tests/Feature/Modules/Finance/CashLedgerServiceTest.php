<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Finance\Data\CashMovementData;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Services\CashLedgerService;
use App\Modules\Finance\Services\CashSessionSummaryService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class CashLedgerServiceTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private CashLedgerService $ledgerService;

    private CashSessionSummaryService $summaryService;

    private CashSession $session;

    private PaymentMethod $cashMethod;

    private PaymentMethod $cardMethod;

    private FinanceConcept $incomeConcept;

    private FinanceConcept $expenseConcept;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
        (new SystemParameterSeeder)->run();
        $this->seedUbigeoFixture();

        $this->ledgerService = app(CashLedgerService::class);
        $this->summaryService = app(CashSessionSummaryService::class);

        $branch = Branch::create([
            'code' => 'B01',
            'name' => 'Sede Central',
            'ubigeo' => '150101',
            'address' => 'Av. Central 123',
            'is_main' => true,
            'is_active' => true,
        ]);

        $box = Box::create([
            'branch_id' => $branch->id,
            'code' => 'CJ01',
            'name' => 'Caja 1',
            'is_active' => true,
        ]);

        /** @var User $user */
        $user = User::factory()->create();

        $this->session = CashSession::create([
            'number' => 'CJ-000001',
            'box_id' => $box->id,
            'branch_id' => $branch->id,
            'status' => CashSessionStatus::Open->value,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'opening_amount' => 100.00,
        ]);

        $this->cashMethod = PaymentMethod::where('code', 'CASH')->firstOrFail();

        $this->cardMethod = PaymentMethod::create([
            'code' => 'VISA',
            'name' => 'Tarjeta Visa',
            'kind' => PaymentMethodKind::Card,
            'settlement' => Settlement::BankAccount,
            'requires_reference' => false,
            'is_active' => true,
        ]);

        $this->incomeConcept = FinanceConcept::create([
            'code' => 'ING_VENTA',
            'name' => 'Ventas contado',
            'direction' => ConceptDirection::Income,
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->expenseConcept = FinanceConcept::create([
            'code' => 'EGR_GASTO',
            'name' => 'Gastos varios',
            'direction' => ConceptDirection::Expense,
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    public function test_expected_cash_only_accumulates_movements_that_affect_cash(): void
    {
        DB::transaction(function () {
            // Movimiento 1: Ingreso en efectivo (+50) -> afecta efectivo
            $this->ledgerService->register(new CashMovementData(
                sessionId: $this->session->id,
                direction: MoneyDirection::In,
                conceptId: $this->incomeConcept->id,
                paymentMethodId: $this->cashMethod->id,
                amount: 50.00,
                description: 'Cobro en efectivo',
            ));

            // Movimiento 2: Ingreso con tarjeta (+80) -> NO afecta efectivo de caja
            $this->ledgerService->register(new CashMovementData(
                sessionId: $this->session->id,
                direction: MoneyDirection::In,
                conceptId: $this->incomeConcept->id,
                paymentMethodId: $this->cardMethod->id,
                amount: 80.00,
                description: 'Cobro con tarjeta',
            ));

            // Movimiento 3: Egreso en efectivo (-20) -> afecta efectivo
            $this->ledgerService->register(new CashMovementData(
                sessionId: $this->session->id,
                direction: MoneyDirection::Out,
                conceptId: $this->expenseConcept->id,
                paymentMethodId: $this->cashMethod->id,
                amount: 20.00,
                description: 'Egreso en efectivo',
            ));
        });

        // Fondo 100 + In efectivo 50 - Out efectivo 20 = 130.00
        $expected = $this->summaryService->expectedCash($this->session);
        $this->assertEquals('130.00', $expected);

        $summary = $this->summaryService->summary($this->session);
        $this->assertEquals(100.0, $summary['opening_amount']);
        $this->assertEquals(50.0, $summary['cash_in']);
        $this->assertEquals(20.0, $summary['cash_out']);
        $this->assertEquals(130.0, $summary['expected_cash']);
    }

    public function test_register_requires_active_database_transaction(): void
    {
        DB::rollBack();

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('CashLedgerService requiere una transacción de base de datos activa.');

            $this->ledgerService->register(new CashMovementData(
                sessionId: $this->session->id,
                direction: MoneyDirection::In,
                conceptId: $this->incomeConcept->id,
                paymentMethodId: $this->cashMethod->id,
                amount: 10.00,
                description: 'Sin transacción',
            ));
        } finally {
            DB::beginTransaction();
        }
    }

    public function test_reverse_requires_active_database_transaction(): void
    {
        /** @var CashMovement $movement */
        $movement = DB::transaction(fn () => $this->ledgerService->register(new CashMovementData(
            sessionId: $this->session->id,
            direction: MoneyDirection::In,
            conceptId: $this->incomeConcept->id,
            paymentMethodId: $this->cashMethod->id,
            amount: 10.00,
            description: 'A revertir',
        )));

        DB::rollBack();

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('CashLedgerService requiere una transacción de base de datos activa.');

            $this->ledgerService->reverse($movement, $movement, $movement->id, 'Sin transacción');
        } finally {
            DB::beginTransaction();
        }
    }

    public function test_cash_movement_model_is_immutable(): void
    {
        /** @var CashMovement $movement */
        $movement = DB::transaction(fn () => $this->ledgerService->register(new CashMovementData(
            sessionId: $this->session->id,
            direction: MoneyDirection::In,
            conceptId: $this->incomeConcept->id,
            paymentMethodId: $this->cashMethod->id,
            amount: 10.00,
            description: 'Inmutable',
        )));

        // Intentar actualizar
        try {
            $movement->amount = 999.00;
            $movement->save();
            $this->fail('Se esperaba LogicException al intentar modificar CashMovement.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('inmutable', $e->getMessage());
        }

        // Intentar eliminar
        try {
            $movement->delete();
            $this->fail('Se esperaba LogicException al intentar eliminar CashMovement.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('no se puede eliminar', $e->getMessage());
        }
    }
}
