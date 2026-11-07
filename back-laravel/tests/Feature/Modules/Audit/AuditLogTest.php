<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Audit;

use App\Modules\Audit\Models\Activity;
use App\Modules\Auth\Models\Session;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Clients\Models\Client;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
    }

    private function authenticateAsAdmin(): User
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $adminRole->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    private function authenticateWithoutPermissions(): User
    {
        $user = User::factory()->create();
        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    public function test_activity_log_is_automatically_created_when_client_is_created_or_updated(): void
    {
        $actor = $this->authenticateAsAdmin();

        // 1. Crear un cliente mediante API
        $createResponse = $this->postJson('/api/v1/clients', [
            'name' => 'Cliente Auditado S.A.',
            'description' => 'Descripción inicial',
            'client_type' => 'company',
            'document_type' => 'RUC',
            'document_number' => '20123456789',
            'default_price_type' => 'retail',
        ]);
        $createResponse->assertCreated();
        $clientId = (string) $createResponse->json('data.id');

        // Verificar que exista registro en activity_log
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'client',
            'subject_id' => $clientId,
            'causer_id' => $actor->id,
            'event' => 'created',
        ]);

        // 2. Actualizar el cliente
        $updateResponse = $this->putJson("/api/v1/clients/{$clientId}", [
            'name' => 'Cliente Auditado Renovado',
            'description' => 'Descripción modificada',
            'client_type' => 'company',
            'document_type' => 'RUC',
            'document_number' => '20123456789',
            'default_price_type' => 'retail',
        ]);
        $updateResponse->assertOk();

        // Verificar que exista registro de actualización con diff en properties
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'client',
            'subject_id' => $clientId,
            'event' => 'updated',
        ]);

        /** @var Activity $latestLog */
        $latestLog = Activity::query()
            ->where('subject_type', 'client')
            ->where('subject_id', $clientId)
            ->where('event', 'updated')
            ->firstOrFail();

        $properties = $latestLog->properties;
        $this->assertNotNull($properties);
        $this->assertSame('CLIENTE AUDITADO S.A.', $properties['old']['name'] ?? null);
        $this->assertSame('CLIENTE AUDITADO RENOVADO', $properties['attributes']['name'] ?? null);
    }

    public function test_audit_logs_can_be_listed_by_user_with_audit_view_permission(): void
    {
        $this->authenticateAsAdmin();

        // Generar un cambio para tener logs
        Client::create([
            'name' => 'Cliente Para Listado',
            'description' => 'Test',
        ]);

        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'log_name',
                        'description',
                        'subject_type',
                        'subject_id',
                        'causer_type',
                        'causer_id',
                        'event',
                        'properties',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    }

    public function test_audit_logs_listing_fails_with_403_for_user_without_permission(): void
    {
        $this->authenticateWithoutPermissions();

        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertForbidden();
    }

    public function test_audit_logs_can_be_filtered_by_subject_type(): void
    {
        $this->authenticateAsAdmin();

        Client::create(['name' => 'Cliente Filtrado', 'description' => 'Test']);
        User::factory()->create();

        $response = $this->getJson('/api/v1/audit-logs?subject_type='.urlencode('client'));

        $response->assertOk();
        $types = collect($response->json('data'))->pluck('subject_type')->unique()->values()->all();
        $this->assertSame(['client'], $types);
    }

    public function test_audit_logs_can_be_filtered_by_session_id(): void
    {
        $actor = $this->authenticateAsAdmin();

        $createResponse = $this->postJson('/api/v1/clients', [
            'name' => 'Cliente Con Sesion',
            'description' => 'Test',
            'client_type' => 'company',
            'document_type' => 'RUC',
            'document_number' => '20123456780',
            'default_price_type' => 'retail',
        ]);
        $createResponse->assertCreated();

        $session = Session::where('user_id', $actor->id)->firstOrFail();

        $response = $this->getJson('/api/v1/audit-logs?session_id='.$session->id);

        $response->assertOk();
        $sessionIds = collect($response->json('data'))->pluck('session_id')->unique()->values()->all();
        $this->assertSame([$session->id], $sessionIds);
    }

    public function test_audit_logs_can_be_filtered_by_branch_id(): void
    {
        $this->authenticateAsAdmin();

        $branchId = (string) Str::uuid();

        Activity::create([
            'log_name' => 'default',
            'description' => 'created',
            'subject_type' => 'client',
            'subject_id' => (string) Str::uuid(),
            'event' => 'created',
            'branch_id' => $branchId,
        ]);

        // Actividad de otra sede, para confirmar que el filtro la excluye.
        Activity::create([
            'log_name' => 'default',
            'description' => 'created',
            'subject_type' => 'client',
            'subject_id' => (string) Str::uuid(),
            'event' => 'created',
            'branch_id' => (string) Str::uuid(),
        ]);

        $response = $this->getJson('/api/v1/audit-logs?branch_id='.$branchId);

        $response->assertOk();
        $branchIds = collect($response->json('data'))->pluck('branch_id')->unique()->values()->all();
        $this->assertSame([$branchId], $branchIds);
    }

    public function test_audit_log_subject_types_are_listed(): void
    {
        $this->authenticateAsAdmin();

        Client::create(['name' => 'Cliente Tipos', 'description' => 'Test']);

        $response = $this->getJson('/api/v1/audit-logs/subject-types');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['value' => 'client', 'title' => 'Client']);
    }

    public function test_audit_log_subject_types_fail_with_403_for_user_without_permission(): void
    {
        $this->authenticateWithoutPermissions();

        $this->getJson('/api/v1/audit-logs/subject-types')->assertForbidden();
    }

    public function test_audit_log_can_be_retrieved_by_id(): void
    {
        $this->authenticateAsAdmin();

        $client = Client::create([
            'name' => 'Cliente Monitoreado',
            'description' => 'Detalle',
        ]);

        /** @var Activity $log */
        $log = Activity::query()
            ->where('subject_type', 'client')
            ->where('subject_id', $client->id)
            ->firstOrFail();

        $response = $this->getJson("/api/v1/audit-logs/{$log->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.subject_id', $client->id);
    }

    public function test_audit_log_returns_404_when_uuid_not_found(): void
    {
        $this->authenticateAsAdmin();

        $randomUuid = (string) Str::uuid();

        $response = $this->getJson("/api/v1/audit-logs/{$randomUuid}");

        $response->assertNotFound();
    }

    public function test_audit_log_returns_404_when_id_is_not_uuid(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/audit-logs/invalid-id-123');

        $response->assertNotFound();
    }

    public function test_user_password_is_not_recorded_in_activity_log(): void
    {
        $this->authenticateAsAdmin();

        $user = User::factory()->create([
            'name' => 'Usuario Audit',
            'email' => 'audit_target@example.com',
            'password' => bcrypt('old-secret-password'),
        ]);

        $user->update([
            'name' => 'Usuario Audit Modificado',
            'password' => bcrypt('new-secret-password'),
        ]);

        /** @var Activity $log */
        $log = Activity::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->firstOrFail();

        $properties = $log->properties;
        $this->assertNotNull($properties);
        $this->assertArrayNotHasKey('password', $properties['attributes'] ?? []);
        $this->assertArrayNotHasKey('password', $properties['old'] ?? []);
    }

    public function test_activity_log_is_created_when_role_is_created_updated_or_deleted(): void
    {
        $actor = $this->authenticateAsAdmin();

        // 1. Crear un rol vía API
        $createResponse = $this->postJson('/api/v1/roles', [
            'name' => 'auditor',
            'description' => 'Rol para auditoría',
            'permissions' => ['audit.view'],
        ]);
        $createResponse->assertCreated();
        $roleId = (string) $createResponse->json('data.id');

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'role',
            'subject_id' => $roleId,
            'causer_id' => $actor->id,
            'event' => 'created',
        ]);

        // 2. Actualizar el rol
        $updateResponse = $this->putJson("/api/v1/roles/{$roleId}", [
            'name' => 'auditor_senior',
            'description' => 'Rol para auditoría senior',
        ]);
        $updateResponse->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'role',
            'subject_id' => $roleId,
            'event' => 'updated',
        ]);

        /** @var Activity $updateLog */
        $updateLog = Activity::query()
            ->where('subject_type', 'role')
            ->where('subject_id', $roleId)
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertSame('auditor', $updateLog->properties['old']['name'] ?? null);
        $this->assertSame('auditor_senior', $updateLog->properties['attributes']['name'] ?? null);

        // 3. Eliminar el rol
        $deleteResponse = $this->deleteJson("/api/v1/roles/{$roleId}");
        $deleteResponse->assertNoContent();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'role',
            'subject_id' => $roleId,
            'event' => 'deleted',
        ]);
    }

    public function test_activity_log_is_created_when_permission_is_created_updated_or_deleted(): void
    {
        $actor = $this->authenticateAsAdmin();

        // 1. Crear permiso vía API
        $createResponse = $this->postJson('/api/v1/permissions', [
            'slug' => 'billing.invoices',
            'name' => 'Ver facturas',
            'module' => 'billing',
            'description' => 'Permite ver facturas',
        ]);
        $createResponse->assertCreated();
        $permissionId = (string) $createResponse->json('data.id');

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'permission',
            'subject_id' => $permissionId,
            'causer_id' => $actor->id,
            'event' => 'created',
        ]);

        // 2. Actualizar permiso
        $updateResponse = $this->putJson("/api/v1/permissions/{$permissionId}", [
            'slug' => 'billing.invoices',
            'name' => 'Ver todas las facturas',
            'description' => 'Descripción actualizada',
        ]);
        $updateResponse->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'permission',
            'subject_id' => $permissionId,
            'event' => 'updated',
        ]);

        // 3. Eliminar permiso
        $deleteResponse = $this->deleteJson("/api/v1/permissions/{$permissionId}");
        $deleteResponse->assertNoContent();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'permission',
            'subject_id' => $permissionId,
            'event' => 'deleted',
        ]);
    }

    public function test_activity_log_is_created_when_only_role_permissions_are_updated(): void
    {
        $actor = $this->authenticateAsAdmin();

        $role = Role::create([
            'name' => 'analyst',
            'description' => 'Analista de datos',
        ]);

        $updateResponse = $this->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'analyst',
            'description' => 'Analista de datos',
            'permissions' => ['audit.view', 'reports.view'],
        ]);
        $updateResponse->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'role',
            'subject_id' => $role->id,
            'causer_id' => $actor->id,
            'event' => 'updated',
        ]);
    }
}
