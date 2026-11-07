<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Enums\CashTransferStatus;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\CashTransfer;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Services\CashSessionSummaryService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\FinanceMastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class CashTransfersApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private Branch $branch;

    private Branch $otherBranch;

    private Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
        (new SystemParameterSeeder)->run();
        $this->seedUbigeoFixture();

        $this->branch = $this->createBranch('LIM-01', 'Sede Principal', true);
        $this->otherBranch = $this->createBranch('LIM-02', 'Sede Secundaria', false);

        /** @var Role $role */
        $role = Role::create([
            'name' => 'cajero',
            'description' => 'Rol de cajero',
        ]);
        $this->cashierRole = $role;
    }

    private function createBranch(string $code, string $name, bool $isMain = false): Branch
    {
        return Branch::create([
            'code' => $code,
            'name' => $name,
            'ubigeo' => '150101',
            'address' => 'Av. Central 456',
            'is_main' => $isMain,
            'is_active' => true,
        ]);
    }

    private function authenticateUser(User $user, Branch $branch): void
    {
        $user->branches()->syncWithoutDetaching([$branch->id => ['is_default' => true]]);
        $this->withHeader('X-Branch-Id', $branch->id);

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);
    }

    private function createBox(string $code, string $name, Branch $branch): Box
    {
        $box = Box::create([
            'branch_id' => $branch->id,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);

        $box->roles()->sync([$this->cashierRole->id]);

        return $box;
    }

    private function openSession(Box $box, User $opener, float $openingAmount = 100.00): CashSession
    {
        return CashSession::factory()->create([
            'box_id' => $box->id,
            'branch_id' => $box->branch_id,
            'opened_by' => $opener->id,
            'opened_at' => now(),
            'opening_amount' => $openingAmount,
        ]);
    }

    public function test_can_create_cash_transfer_in_pending_status(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        $cashier->assignRole($this->cashierRole);
        $cashier->givePermissionTo('cash_transfers.create');

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);
        $session = $this->openSession($sourceBox, $cashier, 100.00);

        $this->authenticateUser($cashier, $this->branch);

        $response = $this->postJson('/api/v1/finance/cash-transfers', [
            'source_session_id' => $session->id,
            'target_box_id' => $targetBox->id,
            'amount' => 40.00,
            'reason' => 'Envío de sencillo para el turno',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '40.00')
            ->assertJsonPath('data.reason', 'Envío de sencillo para el turno')
            ->assertJsonPath('data.source_box.id', $sourceBox->id)
            ->assertJsonPath('data.target_box.id', $targetBox->id);

        $this->assertDatabaseHas('cash_transfers', [
            'source_session_id' => $session->id,
            'target_box_id' => $targetBox->id,
            'status' => CashTransferStatus::Pending->value,
            'amount' => 40.00,
            'created_by' => $cashier->id,
        ]);

        // No se deben haber creado movimientos de dinero aún
        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_cannot_create_transfer_if_amount_exceeds_available_cash(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        $cashier->assignRole($this->cashierRole);
        $cashier->givePermissionTo('cash_transfers.create');

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);
        $session = $this->openSession($sourceBox, $cashier, 100.00);

        // Crear una transferencia previa pendiente de 70.00 (disponible queda en 30.00)
        CashTransfer::factory()->create([
            'source_session_id' => $session->id,
            'source_box_id' => $sourceBox->id,
            'source_branch_id' => $sourceBox->branch_id,
            'target_box_id' => $targetBox->id,
            'target_branch_id' => $targetBox->branch_id,
            'status' => CashTransferStatus::Pending,
            'amount' => 70.00,
            'created_by' => $cashier->id,
        ]);

        $this->authenticateUser($cashier, $this->branch);

        // Intentar transferir 40.00 (70 + 40 = 110 > 100)
        $response = $this->postJson('/api/v1/finance/cash-transfers', [
            'source_session_id' => $session->id,
            'target_box_id' => $targetBox->id,
            'amount' => 40.00,
            'reason' => 'Transferencia que excede disponible',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['Efectivo insuficiente en caja.']);
    }

    public function test_cannot_transfer_to_same_box(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        $cashier->assignRole($this->cashierRole);
        $cashier->givePermissionTo('cash_transfers.create');

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $session = $this->openSession($sourceBox, $cashier, 100.00);

        $this->authenticateUser($cashier, $this->branch);

        $response = $this->postJson('/api/v1/finance/cash-transfers', [
            'source_session_id' => $session->id,
            'target_box_id' => $sourceBox->id,
            'amount' => 20.00,
            'reason' => 'Misma caja destino',
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_create_transfer_from_closed_session(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        $cashier->assignRole($this->cashierRole);
        $cashier->givePermissionTo('cash_transfers.create');

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);
        $session = $this->openSession($sourceBox, $cashier, 100.00);
        $session->update(['status' => 'closed']);

        $this->authenticateUser($cashier, $this->branch);

        $response = $this->postJson('/api/v1/finance/cash-transfers', [
            'source_session_id' => $session->id,
            'target_box_id' => $targetBox->id,
            'amount' => 20.00,
            'reason' => 'Sesión cerrada',
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_create_transfer_if_user_cannot_operate_source_box(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        $cashier->givePermissionTo('cash_transfers.create');
        // No tiene el rol asociado a la caja

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);
        $session = $this->openSession($sourceBox, $cashier, 100.00);

        $this->authenticateUser($cashier, $this->branch);

        $response = $this->postJson('/api/v1/finance/cash-transfers', [
            'source_session_id' => $session->id,
            'target_box_id' => $targetBox->id,
            'amount' => 20.00,
            'reason' => 'Sin permisos de operar',
        ]);

        $response->assertStatus(403);
    }

    public function test_can_approve_cash_transfer(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        $cashier->assignRole($this->cashierRole);
        $cashier->givePermissionTo('cash_transfers.create');

        /** @var User $supervisor */
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo('cash_transfers.approve');

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);

        $sourceSession = $this->openSession($sourceBox, $cashier, 100.00);
        $targetSession = $this->openSession($targetBox, $supervisor, 50.00);

        $transfer = CashTransfer::factory()->create([
            'source_session_id' => $sourceSession->id,
            'source_box_id' => $sourceBox->id,
            'source_branch_id' => $sourceBox->branch_id,
            'target_box_id' => $targetBox->id,
            'target_branch_id' => $targetBox->branch_id,
            'status' => CashTransferStatus::Pending,
            'amount' => 40.00,
            'created_by' => $cashier->id,
        ]);

        $this->authenticateUser($supervisor, $this->branch);

        $response = $this->postJson("/api/v1/finance/cash-transfers/{$transfer->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.target_session_id', $targetSession->id)
            ->assertJsonPath('data.approver.id', $supervisor->id);

        $this->assertDatabaseHas('cash_transfers', [
            'id' => $transfer->id,
            'status' => CashTransferStatus::Approved->value,
            'target_session_id' => $targetSession->id,
            'approved_by' => $supervisor->id,
        ]);

        // Verificar que se crearon dos movimientos de efectivo
        $this->assertDatabaseCount('cash_movements', 2);

        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $sourceSession->id,
            'direction' => 'out',
            'amount' => 40.00,
            'source_type' => 'cash_transfer',
            'source_id' => $transfer->id,
        ]);

        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $targetSession->id,
            'direction' => 'in',
            'amount' => 40.00,
            'source_type' => 'cash_transfer',
            'source_id' => $transfer->id,
        ]);

        // Verificar cuadre de saldos esperados
        $summaryService = app(CashSessionSummaryService::class);
        $this->assertEquals('60.00', $summaryService->expectedCash($sourceSession));
        $this->assertEquals('90.00', $summaryService->expectedCash($targetSession));
    }

    public function test_creator_cannot_approve_own_transfer(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        $cashier->assignRole($this->cashierRole);
        $cashier->givePermissionTo(['cash_transfers.create', 'cash_transfers.approve']);

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);

        $sourceSession = $this->openSession($sourceBox, $cashier, 100.00);
        $this->openSession($targetBox, $cashier, 50.00);

        $transfer = CashTransfer::factory()->create([
            'source_session_id' => $sourceSession->id,
            'source_box_id' => $sourceBox->id,
            'source_branch_id' => $sourceBox->branch_id,
            'target_box_id' => $targetBox->id,
            'target_branch_id' => $targetBox->branch_id,
            'status' => CashTransferStatus::Pending,
            'amount' => 30.00,
            'created_by' => $cashier->id,
        ]);

        $this->authenticateUser($cashier, $this->branch);

        $response = $this->postJson("/api/v1/finance/cash-transfers/{$transfer->id}/approve");

        $response->assertStatus(422)
            ->assertJsonFragment(['No puedes aprobar una transferencia creada por ti mismo.']);
    }

    public function test_cannot_approve_if_target_box_has_no_open_session(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        /** @var User $supervisor */
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo('cash_transfers.approve');

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);

        $sourceSession = $this->openSession($sourceBox, $cashier, 100.00);
        // Caja de destino no tiene sesión abierta

        $transfer = CashTransfer::factory()->create([
            'source_session_id' => $sourceSession->id,
            'source_box_id' => $sourceBox->id,
            'source_branch_id' => $sourceBox->branch_id,
            'target_box_id' => $targetBox->id,
            'target_branch_id' => $targetBox->branch_id,
            'status' => CashTransferStatus::Pending,
            'amount' => 30.00,
            'created_by' => $cashier->id,
        ]);

        $this->authenticateUser($supervisor, $this->branch);

        $response = $this->postJson("/api/v1/finance/cash-transfers/{$transfer->id}/approve");

        $response->assertStatus(422)
            ->assertJsonFragment(['La caja de destino no tiene sesión abierta.']);
    }

    public function test_cannot_approve_if_source_session_was_closed(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        /** @var User $supervisor */
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo('cash_transfers.approve');

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);

        $sourceSession = $this->openSession($sourceBox, $cashier, 100.00);
        $this->openSession($targetBox, $supervisor, 50.00);

        $transfer = CashTransfer::factory()->create([
            'source_session_id' => $sourceSession->id,
            'source_box_id' => $sourceBox->id,
            'source_branch_id' => $sourceBox->branch_id,
            'target_box_id' => $targetBox->id,
            'target_branch_id' => $targetBox->branch_id,
            'status' => CashTransferStatus::Pending,
            'amount' => 30.00,
            'created_by' => $cashier->id,
        ]);

        // Se cierra la sesión de origen antes de aprobar
        $sourceSession->update(['status' => 'closed']);

        $this->authenticateUser($supervisor, $this->branch);

        $response = $this->postJson("/api/v1/finance/cash-transfers/{$transfer->id}/approve");

        $response->assertStatus(422)
            ->assertJsonFragment(['La sesión de origen ya no está abierta.']);
    }

    public function test_cannot_approve_if_source_cash_became_insufficient(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        /** @var User $supervisor */
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo('cash_transfers.approve');

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);

        $sourceSession = $this->openSession($sourceBox, $cashier, 50.00);
        $this->openSession($targetBox, $supervisor, 50.00);

        $transfer = CashTransfer::factory()->create([
            'source_session_id' => $sourceSession->id,
            'source_box_id' => $sourceBox->id,
            'source_branch_id' => $sourceBox->branch_id,
            'target_box_id' => $targetBox->id,
            'target_branch_id' => $targetBox->branch_id,
            'status' => CashTransferStatus::Pending,
            'amount' => 40.00,
            'created_by' => $cashier->id,
        ]);

        // Simular una salida directa de efectivo en la sesión de origen que deja saldo insuficiente
        CashMovement::create([
            'cash_session_id' => $sourceSession->id,
            'box_id' => $sourceBox->id,
            'branch_id' => $sourceBox->branch_id,
            'direction' => 'out',
            'concept_id' => SystemConcept::CashShortage->model()?->id,
            'payment_method_id' => PaymentMethod::where('code', 'CASH')->value('id'),
            'affects_cash' => true,
            'amount' => 30.00,
            'description' => 'Salida directa que consume saldo',
            'created_by' => $cashier->id,
            'moved_at' => now(),
        ]);

        $this->authenticateUser($supervisor, $this->branch);

        // Al intentar aprobar, quedan solo 20.00 y la transferencia pide 40.00
        $response = $this->postJson("/api/v1/finance/cash-transfers/{$transfer->id}/approve");

        $response->assertStatus(422)
            ->assertJsonFragment(['Efectivo insuficiente en caja.']);
    }

    public function test_can_reject_transfer_with_reason(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        /** @var User $supervisor */
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo('cash_transfers.approve');

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);
        $sourceSession = $this->openSession($sourceBox, $cashier, 100.00);

        $transfer = CashTransfer::factory()->create([
            'source_session_id' => $sourceSession->id,
            'source_box_id' => $sourceBox->id,
            'source_branch_id' => $sourceBox->branch_id,
            'target_box_id' => $targetBox->id,
            'target_branch_id' => $targetBox->branch_id,
            'status' => CashTransferStatus::Pending,
            'amount' => 25.00,
            'created_by' => $cashier->id,
        ]);

        $this->authenticateUser($supervisor, $this->branch);

        $response = $this->postJson("/api/v1/finance/cash-transfers/{$transfer->id}/reject", [
            'reason' => 'Caja destino tiene corte de turno en curso',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Caja destino tiene corte de turno en curso')
            ->assertJsonPath('data.rejecter.id', $supervisor->id);

        $this->assertDatabaseHas('cash_transfers', [
            'id' => $transfer->id,
            'status' => CashTransferStatus::Rejected->value,
            'rejected_by' => $supervisor->id,
        ]);

        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_creator_can_cancel_own_transfer(): void
    {
        /** @var User $cashier */
        $cashier = User::factory()->create();
        $cashier->givePermissionTo('cash_transfers.create');

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);
        $sourceSession = $this->openSession($sourceBox, $cashier, 100.00);

        $transfer = CashTransfer::factory()->create([
            'source_session_id' => $sourceSession->id,
            'source_box_id' => $sourceBox->id,
            'source_branch_id' => $sourceBox->branch_id,
            'target_box_id' => $targetBox->id,
            'target_branch_id' => $targetBox->branch_id,
            'status' => CashTransferStatus::Pending,
            'amount' => 25.00,
            'created_by' => $cashier->id,
        ]);

        $this->authenticateUser($cashier, $this->branch);

        $response = $this->postJson("/api/v1/finance/cash-transfers/{$transfer->id}/cancel", [
            'reason' => 'Se canceló por error en el monto solicitado',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Se canceló por error en el monto solicitado')
            ->assertJsonPath('data.canceller.id', $cashier->id);

        $this->assertDatabaseHas('cash_transfers', [
            'id' => $transfer->id,
            'status' => CashTransferStatus::Cancelled->value,
            'cancelled_by' => $cashier->id,
        ]);

        $this->assertDatabaseCount('cash_movements', 0);
    }

    public function test_other_user_cannot_cancel_transfer(): void
    {
        /** @var User $cashierA */
        $cashierA = User::factory()->create();
        /** @var User $cashierB */
        $cashierB = User::factory()->create();
        $cashierB->givePermissionTo('cash_transfers.create');

        $sourceBox = $this->createBox('CJ-01', 'Caja Principal', $this->branch);
        $targetBox = $this->createBox('CJ-02', 'Caja Secundaria', $this->branch);
        $sourceSession = $this->openSession($sourceBox, $cashierA, 100.00);

        $transfer = CashTransfer::factory()->create([
            'source_session_id' => $sourceSession->id,
            'source_box_id' => $sourceBox->id,
            'source_branch_id' => $sourceBox->branch_id,
            'target_box_id' => $targetBox->id,
            'target_branch_id' => $targetBox->branch_id,
            'status' => CashTransferStatus::Pending,
            'amount' => 25.00,
            'created_by' => $cashierA->id,
        ]);

        $this->authenticateUser($cashierB, $this->branch);

        $response = $this->postJson("/api/v1/finance/cash-transfers/{$transfer->id}/cancel", [
            'reason' => 'Intento de cancelar ajena',
        ]);

        $response->assertStatus(403);
    }

    public function test_list_cash_transfers_with_filters_and_pending_count(): void
    {
        /** @var User $supervisor */
        $supervisor = User::factory()->create();
        $supervisor->givePermissionTo(['cash_transfers.view', 'cash_transfers.approve']);

        $boxA = $this->createBox('CJ-01', 'Caja A', $this->branch);
        $boxB = $this->createBox('CJ-02', 'Caja B', $this->branch);
        $boxC = $this->createBox('CJ-03', 'Caja Otra Sede', $this->otherBranch);

        $sessionA = $this->openSession($boxA, $supervisor, 200.00);

        // Transferencia enviada desde Sede Principal a Otra Sede
        CashTransfer::factory()->create([
            'source_session_id' => $sessionA->id,
            'source_box_id' => $boxA->id,
            'source_branch_id' => $this->branch->id,
            'target_box_id' => $boxC->id,
            'target_branch_id' => $this->otherBranch->id,
            'status' => CashTransferStatus::Pending,
            'amount' => 50.00,
            'created_by' => $supervisor->id,
        ]);

        // Transferencia interna aprobada
        CashTransfer::factory()->create([
            'source_session_id' => $sessionA->id,
            'source_box_id' => $boxA->id,
            'source_branch_id' => $this->branch->id,
            'target_box_id' => $boxB->id,
            'target_branch_id' => $this->branch->id,
            'status' => CashTransferStatus::Approved,
            'amount' => 30.00,
            'created_by' => $supervisor->id,
        ]);

        $this->authenticateUser($supervisor, $this->branch);

        // Listar todas
        $response = $this->getJson('/api/v1/finance/cash-transfers');
        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        // Filtrar por estado pending
        $responsePending = $this->getJson('/api/v1/finance/cash-transfers?status=pending');
        $responsePending->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'pending');

        // Endpoint de conteo de pendientes
        $responseCount = $this->getJson('/api/v1/finance/cash-transfers/pending-count');
        $responseCount->assertStatus(200)
            ->assertJsonPath('data.count', 1);
    }
}
