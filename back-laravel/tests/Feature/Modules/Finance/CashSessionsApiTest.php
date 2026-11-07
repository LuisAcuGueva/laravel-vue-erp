<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Enums\CashReviewStatus;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashSession;
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

class CashSessionsApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new FinanceMastersSeeder)->run();
        (new SystemParameterSeeder)->run();
        $this->seedUbigeoFixture();
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

    private function createCashier(Branch $branch, Role $role): User
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->givePermissionTo(Permission::where('slug', 'cash_sessions.operate')->firstOrFail());

        return $user;
    }

    private function createSupervisor(Branch $branch): User
    {
        /** @var User $user */
        $user = User::factory()->create();
        $managerRole = Role::where('name', 'manager')->firstOrFail();
        $user->assignRole($managerRole);

        return $user;
    }

    public function test_cashier_can_open_session(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $userRole = Role::where('name', 'user')->firstOrFail();

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ01', 'name' => 'Caja 1', 'is_active' => true]);
        $box->roles()->sync([$userRole->id]);

        $cashier = $this->createCashier($branch, $userRole);
        $this->authenticateUser($cashier, $branch);

        $response = $this->postJson('/api/v1/finance/cash-sessions', [
            'box_id' => $box->id,
            'opening_amount' => 150.00,
            'opening_notes' => 'Apertura de turno mañana',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.opening_amount', 150)
            ->assertJsonPath('data.number', 'CJ-000001');

        $sessionId = (string) $response->json('data.id');

        $this->assertDatabaseHas('cash_sessions', [
            'box_id' => $box->id,
            'status' => 'open',
            'opened_by' => $cashier->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'cash_session',
            'subject_id' => $sessionId,
            'causer_id' => $cashier->id,
            'event' => 'created',
        ]);
    }

    public function test_cannot_open_second_session_on_same_box(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $userRole = Role::where('name', 'user')->firstOrFail();

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ01', 'name' => 'Caja 1', 'is_active' => true]);
        $box->roles()->sync([$userRole->id]);

        $cashier = $this->createCashier($branch, $userRole);
        $this->authenticateUser($cashier, $branch);

        $this->postJson('/api/v1/finance/cash-sessions', [
            'box_id' => $box->id,
            'opening_amount' => 100.00,
        ])->assertCreated();

        // Segunda apertura -> 409 Conflict
        $response = $this->postJson('/api/v1/finance/cash-sessions', [
            'box_id' => $box->id,
            'opening_amount' => 200.00,
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('errors.session.0', 'La caja ya tiene una sesión abierta (CJ-000001).');
    }

    public function test_user_without_box_role_cannot_open_session(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $userRole = Role::where('name', 'user')->firstOrFail();
        $managerRole = Role::where('name', 'manager')->firstOrFail();

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ01', 'name' => 'Caja VIP', 'is_active' => true]);
        $box->roles()->sync([$managerRole->id]); // Solo manager

        $cashier = $this->createCashier($branch, $userRole); // Es user
        $this->authenticateUser($cashier, $branch);

        $response = $this->postJson('/api/v1/finance/cash-sessions', [
            'box_id' => $box->id,
            'opening_amount' => 100.00,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_open_session_in_non_active_branch(): void
    {
        $branch1 = $this->createBranch('B01', 'Sede Central', true);
        $branch2 = $this->createBranch('B02', 'Sede Norte');
        $userRole = Role::where('name', 'user')->firstOrFail();

        $boxBranch2 = Box::create(['branch_id' => $branch2->id, 'code' => 'CJ02', 'name' => 'Caja Norte', 'is_active' => true]);
        $boxBranch2->roles()->sync([$userRole->id]);

        $cashier = $this->createCashier($branch1, $userRole);
        $cashier->branches()->sync([$branch1->id, $branch2->id]); // Tiene acceso a ambas
        $this->authenticateUser($cashier, $branch1); // Pero su sede activa es Sede 1

        $response = $this->postJson('/api/v1/finance/cash-sessions', [
            'box_id' => $boxBranch2->id,
            'opening_amount' => 100.00,
        ]);

        $response->assertStatus(403);
    }

    public function test_can_close_session_within_tolerance_automatically_approves(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $userRole = Role::where('name', 'user')->firstOrFail();

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ01', 'name' => 'Caja 1', 'is_active' => true]);
        $box->roles()->sync([$userRole->id]);

        $cashier = $this->createCashier($branch, $userRole);
        $this->authenticateUser($cashier, $branch);

        $openRes = $this->postJson('/api/v1/finance/cash-sessions', [
            'box_id' => $box->id,
            'opening_amount' => 200.00,
        ]);
        $sessionId = (string) $openRes->json('data.id');

        // Cerrar con conteo exacto de 200 (1 billete de 200)
        $response = $this->postJson("/api/v1/finance/cash-sessions/{$sessionId}/close", [
            'count' => [
                ['denomination' => 200, 'quantity' => 1],
            ],
            'closing_notes' => 'Cierre cuadra perfecto',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.counted_cash', 200)
            ->assertJsonPath('data.expected_cash', 200)
            ->assertJsonPath('data.difference', 0)
            ->assertJsonPath('data.review_status', 'approved');

        $this->assertDatabaseHas('cash_count_details', [
            'cash_session_id' => $sessionId,
            'denomination' => 200,
            'quantity' => 1,
            'subtotal' => 200,
        ]);
    }

    public function test_closing_outside_tolerance_requires_notes_and_sets_pending(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $userRole = Role::where('name', 'user')->firstOrFail();

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ01', 'name' => 'Caja 1', 'is_active' => true]);
        $box->roles()->sync([$userRole->id]);

        $cashier = $this->createCashier($branch, $userRole);
        $this->authenticateUser($cashier, $branch);

        $openRes = $this->postJson('/api/v1/finance/cash-sessions', [
            'box_id' => $box->id,
            'opening_amount' => 100.00,
        ]);
        $sessionId = (string) $openRes->json('data.id');

        // Cerrar con diferencia sin notas -> 422
        $this->postJson("/api/v1/finance/cash-sessions/{$sessionId}/close", [
            'counted_cash' => 150.00,
            'closing_notes' => null,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('closing_notes');

        // Cerrar con diferencia y notas -> pending
        $res = $this->postJson("/api/v1/finance/cash-sessions/{$sessionId}/close", [
            'counted_cash' => 150.00,
            'closing_notes' => 'Hay un sobrante de 50 soles por verificar',
        ]);

        $res->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.difference', 50)
            ->assertJsonPath('data.review_status', 'pending');
    }

    public function test_closing_with_invalid_denomination_fails(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $userRole = Role::where('name', 'user')->firstOrFail();

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ01', 'name' => 'Caja 1', 'is_active' => true]);
        $box->roles()->sync([$userRole->id]);

        $cashier = $this->createCashier($branch, $userRole);
        $this->authenticateUser($cashier, $branch);

        $openRes = $this->postJson('/api/v1/finance/cash-sessions', [
            'box_id' => $box->id,
            'opening_amount' => 100.00,
        ]);
        $sessionId = (string) $openRes->json('data.id');

        // Denominación 77 no existe
        $response = $this->postJson("/api/v1/finance/cash-sessions/{$sessionId}/close", [
            'count' => [
                ['denomination' => 77, 'quantity' => 1],
            ],
            'closing_notes' => 'Conteo con billete raro',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('count');
    }

    public function test_closer_cannot_review_own_session(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $managerRole = Role::where('name', 'manager')->firstOrFail();

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ01', 'name' => 'Caja 1', 'is_active' => true]);
        $box->roles()->sync([$managerRole->id]);

        $supervisor = $this->createSupervisor($branch);
        $this->authenticateUser($supervisor, $branch);

        // El supervisor abre y cierra con descuadre
        $session = CashSession::create([
            'number' => 'CJ-000010',
            'box_id' => $box->id,
            'branch_id' => $branch->id,
            'status' => CashSessionStatus::Closed->value,
            'opened_by' => $supervisor->id,
            'opened_at' => now()->subHours(4),
            'closed_by' => $supervisor->id,
            'closed_at' => now(),
            'opening_amount' => 100.00,
            'expected_cash' => 100.00,
            'counted_cash' => 120.00,
            'difference' => 20.00,
            'review_status' => CashReviewStatus::Pending->value,
            'closing_notes' => 'Descuadre propio',
        ]);

        // Intento de auto-revisión -> 422
        $response = $this->postJson("/api/v1/finance/cash-sessions/{$session->id}/review", [
            'decision' => 'approved',
            'notes' => 'Me auto-apruebo',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('reviewer');
    }

    public function test_supervisor_can_approve_review_and_creates_informative_movement(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $userRole = Role::where('name', 'user')->firstOrFail();

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ01', 'name' => 'Caja 1', 'is_active' => true]);
        $box->roles()->sync([$userRole->id]);

        $cashier = $this->createCashier($branch, $userRole);
        $supervisor = $this->createSupervisor($branch);

        $session = CashSession::create([
            'number' => 'CJ-000011',
            'box_id' => $box->id,
            'branch_id' => $branch->id,
            'status' => CashSessionStatus::Closed->value,
            'opened_by' => $cashier->id,
            'opened_at' => now()->subHours(4),
            'closed_by' => $cashier->id,
            'closed_at' => now(),
            'opening_amount' => 100.00,
            'expected_cash' => 100.00,
            'counted_cash' => 140.00,
            'difference' => 40.00,
            'review_status' => CashReviewStatus::Pending->value,
            'closing_notes' => 'Sobrante de 40',
        ]);

        // Supervisor aprueba la revisión
        $this->authenticateUser($supervisor, $branch);

        $response = $this->postJson("/api/v1/finance/cash-sessions/{$session->id}/review", [
            'decision' => 'approved',
            'notes' => 'Aprobado ajuste por sobrante',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.review_status', 'approved')
            ->assertJsonPath('data.reviewed_by', $supervisor->id);

        // Movimiento informativo creado con affects_cash = false
        $this->assertDatabaseHas('cash_movements', [
            'cash_session_id' => $session->id,
            'direction' => 'in',
            'affects_cash' => false,
            'amount' => 40.00,
            'is_manual' => false,
        ]);
    }

    public function test_supervisor_can_reject_review_with_notes(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $userRole = Role::where('name', 'user')->firstOrFail();

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ01', 'name' => 'Caja 1', 'is_active' => true]);
        $cashier = $this->createCashier($branch, $userRole);
        $supervisor = $this->createSupervisor($branch);

        $session = CashSession::create([
            'number' => 'CJ-000012',
            'box_id' => $box->id,
            'branch_id' => $branch->id,
            'status' => CashSessionStatus::Closed->value,
            'opened_by' => $cashier->id,
            'opened_at' => now()->subHours(4),
            'closed_by' => $cashier->id,
            'closed_at' => now(),
            'opening_amount' => 100.00,
            'expected_cash' => 100.00,
            'counted_cash' => 50.00,
            'difference' => -50.00,
            'review_status' => CashReviewStatus::Pending->value,
            'closing_notes' => 'Faltante de 50',
        ]);

        $this->authenticateUser($supervisor, $branch);

        // Rechazar sin notas -> 422
        $this->postJson("/api/v1/finance/cash-sessions/{$session->id}/review", [
            'decision' => 'rejected',
            'notes' => null,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('notes');

        // Rechazar con notas -> 200
        $this->postJson("/api/v1/finance/cash-sessions/{$session->id}/review", [
            'decision' => 'rejected',
            'notes' => 'No se justifica el faltante de 50 soles, pasar a auditoría.',
        ])->assertOk()
            ->assertJsonPath('data.review_status', 'rejected');
    }
}
