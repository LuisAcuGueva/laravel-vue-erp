<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
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
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class CashMovementsApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private Branch $branch;

    private User $cashier;

    private Box $box;

    private CashSession $session;

    private PaymentMethod $cashMethod;

    private PaymentMethod $bankMethod;

    private FinanceConcept $incomeConcept;

    private FinanceConcept $expenseConcept;

    private FinanceConcept $systemConcept;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
        (new SystemParameterSeeder)->run();
        $this->seedUbigeoFixture();

        $this->branch = Branch::create([
            'code' => 'B01',
            'name' => 'Sede Central',
            'ubigeo' => '150101',
            'address' => 'Av. Central 789',
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
        $cashier->givePermissionTo(Permission::where('slug', 'cash_sessions.operate')->firstOrFail());
        $cashier->givePermissionTo(Permission::where('slug', 'cash_movements.cancel')->firstOrFail());
        $cashier->branches()->sync([$this->branch->id => ['is_default' => true]]);
        $this->cashier = $cashier;

        $this->withHeader('X-Branch-Id', $this->branch->id);
        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($this->cashier);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        // Sesión abierta con 100 de fondo
        $this->session = CashSession::create([
            'number' => 'CJ-000001',
            'box_id' => $this->box->id,
            'branch_id' => $this->branch->id,
            'status' => CashSessionStatus::Open->value,
            'opened_by' => $this->cashier->id,
            'opened_at' => now(),
            'opening_amount' => 100.00,
        ]);

        $this->cashMethod = PaymentMethod::where('code', 'CASH')->firstOrFail();

        $this->bankMethod = PaymentMethod::create([
            'code' => 'TRANSFER',
            'name' => 'Transferencia Bancaria',
            'kind' => PaymentMethodKind::Transfer,
            'settlement' => Settlement::BankAccount,
            'requires_reference' => true,
            'is_active' => true,
        ]);

        $this->incomeConcept = FinanceConcept::create([
            'code' => 'OTROS_ING',
            'name' => 'Otros ingresos varios',
            'direction' => ConceptDirection::Income,
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->expenseConcept = FinanceConcept::create([
            'code' => 'SERV_BASIC',
            'name' => 'Pago servicios básicos',
            'direction' => ConceptDirection::Expense,
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->systemConcept = FinanceConcept::where('is_system', true)->firstOrFail();
    }

    public function test_cashier_can_record_manual_income_movement(): void
    {
        $response = $this->postJson("/api/v1/finance/cash-sessions/{$this->session->id}/movements", [
            'direction' => 'in',
            'concept_id' => $this->incomeConcept->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => 80.50,
            'description' => 'Ingreso por reciclaje',
            'counterparty_name' => 'Recicladora Perú',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.direction', 'in')
            ->assertJsonPath('data.amount', 80.5)
            ->assertJsonPath('data.affects_cash', true)
            ->assertJsonPath('data.is_manual', true);

        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $this->session->id,
            'amount' => 80.50,
            'direction' => 'in',
            'is_manual' => true,
        ]);
    }

    public function test_cashier_can_record_manual_expense_movement(): void
    {
        $response = $this->postJson("/api/v1/finance/cash-sessions/{$this->session->id}/movements", [
            'direction' => 'out',
            'concept_id' => $this->expenseConcept->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => 45.00,
            'description' => 'Pago de recibo de agua',
            'counterparty_name' => 'Sedapal',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.direction', 'out')
            ->assertJsonPath('data.amount', 45)
            ->assertJsonPath('data.affects_cash', true);
    }

    public function test_expense_that_exceeds_available_cash_fails_with_422(): void
    {
        // La sesión tiene 100 de fondo
        $response = $this->postJson("/api/v1/finance/cash-sessions/{$this->session->id}/movements", [
            'direction' => 'out',
            'concept_id' => $this->expenseConcept->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => 150.00, // Excede los 100 disponibles
            'description' => 'Pago excesivo',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Efectivo insuficiente en caja.');
    }

    public function test_cannot_use_system_concept_for_manual_movement(): void
    {
        $response = $this->postJson("/api/v1/finance/cash-sessions/{$this->session->id}/movements", [
            'direction' => 'in',
            'concept_id' => $this->systemConcept->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => 30.00,
            'description' => 'Intento de usar concepto de sistema',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('concept_id');
    }

    public function test_cannot_use_bank_settlement_payment_method_for_manual_movement(): void
    {
        $response = $this->postJson("/api/v1/finance/cash-sessions/{$this->session->id}/movements", [
            'direction' => 'in',
            'concept_id' => $this->incomeConcept->id,
            'payment_method_id' => $this->bankMethod->id,
            'amount' => 30.00,
            'description' => 'Intento de usar método banco',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('payment_method_id');
    }

    public function test_cannot_use_concept_with_mismatched_direction(): void
    {
        // Usar concepto de gasto (expense) en movimiento de ingreso (in)
        $response = $this->postJson("/api/v1/finance/cash-sessions/{$this->session->id}/movements", [
            'direction' => 'in',
            'concept_id' => $this->expenseConcept->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => 20.00,
            'description' => 'Dirección contraria',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('concept_id');
    }

    public function test_cancel_manual_movement_creates_reversal(): void
    {
        $createRes = $this->postJson("/api/v1/finance/cash-sessions/{$this->session->id}/movements", [
            'direction' => 'in',
            'concept_id' => $this->incomeConcept->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => 60.00,
            'description' => 'Ingreso a anular',
        ]);
        $movementId = (string) $createRes->json('data.id');

        // Anular
        $cancelRes = $this->postJson("/api/v1/finance/cash-movements/{$movementId}/cancel", [
            'reason' => 'Error de tipeo en el monto',
        ]);

        $cancelRes->assertOk()
            ->assertJsonPath('data.direction', 'out')
            ->assertJsonPath('data.amount', 60)
            ->assertJsonPath('data.reverses_id', $movementId);

        $this->assertDatabaseHas('cash_movements', [
            'reverses_id' => $movementId,
            'direction' => 'out',
        ]);
    }

    public function test_cannot_cancel_already_cancelled_movement(): void
    {
        $createRes = $this->postJson("/api/v1/finance/cash-sessions/{$this->session->id}/movements", [
            'direction' => 'in',
            'concept_id' => $this->incomeConcept->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => 60.00,
            'description' => 'Ingreso',
        ]);
        $movementId = (string) $createRes->json('data.id');

        // Primera anulación -> 200
        $this->postJson("/api/v1/finance/cash-movements/{$movementId}/cancel", [
            'reason' => 'Anulación 1',
        ])->assertOk();

        // Segunda anulación -> 422
        $this->postJson("/api/v1/finance/cash-movements/{$movementId}/cancel", [
            'reason' => 'Anulación 2',
        ])->assertStatus(422);
    }

    public function test_cannot_cancel_non_manual_movement(): void
    {
        // Crear un movimiento no manual (ej. generado por sistema/venta)
        $nonManual = CashMovement::create([
            'cash_session_id' => $this->session->id,
            'box_id' => $this->box->id,
            'branch_id' => $this->branch->id,
            'direction' => MoneyDirection::In->value,
            'concept_id' => $this->incomeConcept->id,
            'payment_method_id' => $this->cashMethod->id,
            'affects_cash' => true,
            'amount' => 100.00,
            'description' => 'Venta en POS',
            'is_manual' => false,
            'moved_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/finance/cash-movements/{$nonManual->id}/cancel", [
            'reason' => 'Intento de anulación directa',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Solo se pueden anular movimientos manuales de caja desde este endpoint.');
    }

    public function test_cannot_cancel_movement_in_closed_session(): void
    {
        $createRes = $this->postJson("/api/v1/finance/cash-sessions/{$this->session->id}/movements", [
            'direction' => 'in',
            'concept_id' => $this->incomeConcept->id,
            'payment_method_id' => $this->cashMethod->id,
            'amount' => 50.00,
            'description' => 'Movimiento de prueba',
        ]);
        $movementId = (string) $createRes->json('data.id');

        // Cerrar sesión
        $this->session->update(['status' => CashSessionStatus::Closed->value]);

        $response = $this->postJson("/api/v1/finance/cash-movements/{$movementId}/cancel", [
            'reason' => 'Intento tras cierre',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'La sesión del movimiento ya está cerrada.');
    }
}
