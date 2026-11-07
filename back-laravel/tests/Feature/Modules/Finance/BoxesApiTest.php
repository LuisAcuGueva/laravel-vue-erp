<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Finance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Maintenance\Exceptions\BranchRuleException;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Services\BranchService;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class BoxesApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        (new SystemParameterSeeder)->run();
        $this->seedUbigeoFixture();
    }

    private function createBranch(string $code, string $name, bool $isMain = false): Branch
    {
        return Branch::create([
            'code' => $code,
            'name' => $name,
            'ubigeo' => '150101',
            'address' => 'Av. Principal 123',
            'is_main' => $isMain,
            'is_active' => true,
        ]);
    }

    private function authenticateAsAdmin(?Branch $activeBranch = null): User
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $adminRole->syncPermissions(Permission::all());

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole($adminRole);

        if ($activeBranch !== null) {
            $user->branches()->sync([$activeBranch->id => ['is_default' => true]]);
            $this->withHeader('X-Branch-Id', $activeBranch->id);
        }

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    private function authenticateAsCashier(Branch $branch, ?Role $role = null): User
    {
        /** @var User $user */
        $user = User::factory()->create();

        if ($role !== null) {
            $user->assignRole($role);
        }

        $user->givePermissionTo(Permission::where('slug', 'cash_sessions.operate')->firstOrFail());
        $user->branches()->sync([$branch->id => ['is_default' => true]]);

        $this->withHeader('X-Branch-Id', $branch->id);

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    public function test_admin_can_list_and_filter_boxes(): void
    {
        $branch1 = $this->createBranch('B01', 'Sede Central', true);
        $branch2 = $this->createBranch('B02', 'Sede Norte');

        $this->authenticateAsAdmin($branch1);

        Box::create(['branch_id' => $branch1->id, 'code' => 'CJ01', 'name' => 'Caja Central 1', 'is_active' => true]);
        Box::create(['branch_id' => $branch1->id, 'code' => 'CJ02', 'name' => 'Caja Central 2', 'is_active' => false]);
        Box::create(['branch_id' => $branch2->id, 'code' => 'CJ03', 'name' => 'Caja Norte 1', 'is_active' => true]);

        // Listar todas
        $response = $this->getJson('/api/v1/finance/boxes');
        $response->assertOk()
            ->assertJsonPath('meta.total', 3);

        // Filtrar por sede
        $response = $this->getJson('/api/v1/finance/boxes?branch_id='.$branch1->id);
        $response->assertOk()
            ->assertJsonPath('meta.total', 2);

        // Filtrar por estado activo
        $response = $this->getJson('/api/v1/finance/boxes?branch_id='.$branch1->id.'&is_active=1');
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'CJ01');

        // El frontend envía is_active como literal "true"/"false" (no "1"/"0")
        $response = $this->getJson('/api/v1/finance/boxes?branch_id='.$branch1->id.'&is_active=true');
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'CJ01');

        $response = $this->getJson('/api/v1/finance/boxes?branch_id='.$branch1->id.'&is_active=false');
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'CJ02');

        // Búsqueda por texto
        $response = $this->getJson('/api/v1/finance/boxes?search=Norte');
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'CJ03');
    }

    public function test_admin_can_create_box_with_roles(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $this->authenticateAsAdmin($branch);

        $userRole = Role::where('name', 'user')->firstOrFail();

        $payload = [
            'branch_id' => $branch->id,
            'code' => 'CJ10',
            'name' => 'Caja Mostrador',
            'description' => 'Caja rápida de atención al cliente',
            'is_active' => true,
            'role_ids' => [$userRole->id],
        ];

        $response = $this->postJson('/api/v1/finance/boxes', $payload);
        $response->assertCreated()
            ->assertJsonPath('data.code', 'CJ10')
            ->assertJsonPath('data.name', 'Caja Mostrador')
            ->assertJsonPath('data.roles.0.name', 'user');

        $this->assertDatabaseHas('boxes', [
            'code' => 'CJ10',
            'branch_id' => $branch->id,
        ]);

        $this->assertDatabaseHas('box_roles', [
            'role_id' => $userRole->id,
        ]);
    }

    public function test_admin_can_show_and_update_box(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $this->authenticateAsAdmin($branch);

        $box = Box::create([
            'branch_id' => $branch->id,
            'code' => 'CJ20',
            'name' => 'Caja Temporal',
            'is_active' => true,
        ]);

        $showResponse = $this->getJson("/api/v1/finance/boxes/{$box->id}");
        $showResponse->assertOk()
            ->assertJsonPath('data.id', $box->id)
            ->assertJsonPath('data.code', 'CJ20');

        $updateResponse = $this->putJson("/api/v1/finance/boxes/{$box->id}", [
            'name' => 'Caja Definitiva',
            'description' => 'Descripción actualizada',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Caja Definitiva')
            ->assertJsonPath('data.description', 'Descripción actualizada');
    }

    public function test_sync_roles_updates_authorized_roles(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $this->authenticateAsAdmin($branch);

        $box = Box::create([
            'branch_id' => $branch->id,
            'code' => 'CJ30',
            'name' => 'Caja Roles',
            'is_active' => true,
        ]);

        $userRole = Role::where('name', 'user')->firstOrFail();
        $managerRole = Role::where('name', 'manager')->firstOrFail();

        $response = $this->putJson("/api/v1/finance/boxes/{$box->id}/roles", [
            'role_ids' => [$userRole->id, $managerRole->id],
        ]);

        $response->assertOk();
        $this->assertCount(2, $box->fresh()->roles);

        // Desasignar rol
        $this->putJson("/api/v1/finance/boxes/{$box->id}/roles", [
            'role_ids' => [$managerRole->id],
        ])->assertOk();

        $this->assertCount(1, $box->fresh()->roles);
    }

    public function test_available_boxes_filters_by_role_and_active_branch(): void
    {
        $branch1 = $this->createBranch('B01', 'Sede Central', true);
        $branch2 = $this->createBranch('B02', 'Sede Norte');

        $userRole = Role::where('name', 'user')->firstOrFail();
        $managerRole = Role::where('name', 'manager')->firstOrFail();

        $boxForUser = Box::create(['branch_id' => $branch1->id, 'code' => 'CJ-U', 'name' => 'Caja Usuario', 'is_active' => true]);
        $boxForUser->roles()->sync([$userRole->id]);

        $boxForManager = Box::create(['branch_id' => $branch1->id, 'code' => 'CJ-M', 'name' => 'Caja Manager', 'is_active' => true]);
        $boxForManager->roles()->sync([$managerRole->id]);

        $boxBranch2 = Box::create(['branch_id' => $branch2->id, 'code' => 'CJ-B2', 'name' => 'Caja Sede 2', 'is_active' => true]);
        $boxBranch2->roles()->sync([$userRole->id]);

        // Cajero con rol 'user' en Sede 1 solo ve CJ-U
        $this->authenticateAsCashier($branch1, $userRole);
        $response = $this->getJson('/api/v1/finance/boxes/available');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'CJ-U');

        // Admin en Sede 1 ve todas las activas de Sede 1 (CJ-U y CJ-M), pero no la de Sede 2
        $this->authenticateAsAdmin($branch1);
        $adminResponse = $this->getJson('/api/v1/finance/boxes/available');
        $adminResponse->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_deactivate_or_delete_box_with_open_session(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $admin = $this->authenticateAsAdmin($branch);

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ-ACT', 'name' => 'Caja Activa', 'is_active' => true]);

        // Crear sesión abierta
        CashSession::create([
            'number' => 'CJ-000001',
            'box_id' => $box->id,
            'branch_id' => $branch->id,
            'status' => CashSessionStatus::Open->value,
            'opened_by' => $admin->id,
            'opened_at' => now(),
            'opening_amount' => 100.00,
        ]);

        // Intento de desactivar -> 422
        $this->putJson("/api/v1/finance/boxes/{$box->id}", [
            'is_active' => false,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('is_active');

        // Intento de eliminar -> 422
        $this->deleteJson("/api/v1/finance/boxes/{$box->id}")
            ->assertStatus(422);
    }

    public function test_cannot_delete_box_with_past_sessions(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $admin = $this->authenticateAsAdmin($branch);

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ-HIST', 'name' => 'Caja Histórica', 'is_active' => true]);

        // Crear sesión cerrada
        CashSession::create([
            'number' => 'CJ-000002',
            'box_id' => $box->id,
            'branch_id' => $branch->id,
            'status' => CashSessionStatus::Closed->value,
            'opened_by' => $admin->id,
            'opened_at' => now()->subDay(),
            'closed_by' => $admin->id,
            'closed_at' => now()->subDay()->addHours(8),
            'opening_amount' => 100.00,
            'counted_cash' => 500.00,
            'expected_cash' => 500.00,
            'difference' => 0.00,
        ]);

        // Intento de eliminar caja con sesiones -> 422
        $this->deleteJson("/api/v1/finance/boxes/{$box->id}")
            ->assertStatus(422);
    }

    public function test_cannot_delete_branch_with_registered_boxes(): void
    {
        $branch = $this->createBranch('B01', 'Sede Sucursal', false);
        Box::create(['branch_id' => $branch->id, 'code' => 'CJ-BR', 'name' => 'Caja Sede', 'is_active' => true]);

        $this->expectException(BranchRuleException::class);
        $this->expectExceptionMessage('La sede tiene cajas registradas.');

        app(BranchService::class)->delete($branch);
    }

    public function test_suggested_opening_returns_counted_cash_from_last_closed_session(): void
    {
        $branch = $this->createBranch('B01', 'Sede Central', true);
        $admin = $this->authenticateAsAdmin($branch);

        $box = Box::create(['branch_id' => $branch->id, 'code' => 'CJ-SUG', 'name' => 'Caja Sugerida', 'is_active' => true]);

        // Sin sesiones -> 0.00
        $res = $this->getJson("/api/v1/finance/boxes/{$box->id}/suggested-opening");
        $res->assertOk()
            ->assertJsonPath('data.suggested_amount', 0);

        // Crear una sesión cerrada con 425.50
        CashSession::create([
            'number' => 'CJ-000003',
            'box_id' => $box->id,
            'branch_id' => $branch->id,
            'status' => CashSessionStatus::Closed->value,
            'opened_by' => $admin->id,
            'opened_at' => now()->subDay(),
            'closed_by' => $admin->id,
            'closed_at' => now()->subDay()->addHours(8),
            'opening_amount' => 100.00,
            'counted_cash' => 425.50,
            'expected_cash' => 425.50,
            'difference' => 0.00,
        ]);

        $res2 = $this->getJson("/api/v1/finance/boxes/{$box->id}/suggested-opening");
        $res2->assertOk()
            ->assertJsonPath('data.suggested_amount', 425.5);
    }
}
