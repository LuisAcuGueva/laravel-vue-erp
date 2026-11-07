<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Roles;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RolesApiTest extends TestCase
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

    private function authenticateWithPermissions(array $permissions, ?Role $customRole = null): User
    {
        $user = User::factory()->create();

        if ($customRole) {
            $user->assignRole($customRole);
        }

        foreach ($permissions as $slug) {
            $perm = Permission::where('slug', $slug)->first() ?? Permission::create([
                'slug' => $slug,
                'name' => $slug,
                'module' => explode('.', $slug)[0],
            ]);
            $user->givePermissionTo($perm);
        }

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    public function test_roles_can_be_listed_with_pagination_and_search(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/roles');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'permissions',
                        'users_count',
                        'is_protected',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        // Search test
        $searchResponse = $this->getJson('/api/v1/roles?search=admin');
        $searchResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'admin')
            ->assertJsonPath('data.0.is_protected', true);
    }

    public function test_roles_listing_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/roles');
        $response->assertUnauthorized();
    }

    public function test_roles_listing_requires_permission(): void
    {
        $this->authenticateWithPermissions(['clients.view']);

        $response = $this->getJson('/api/v1/roles');
        $response->assertForbidden();
    }

    public function test_role_can_be_created_with_permissions(): void
    {
        $this->authenticateAsAdmin();

        $payload = [
            'name' => 'supervisor',
            'description' => 'Supervisa operaciones y clientes',
            'permissions' => ['clients.view', 'clients.create'],
        ];

        $response = $this->postJson('/api/v1/roles', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'supervisor')
            ->assertJsonPath('data.description', 'Supervisa operaciones y clientes')
            ->assertJsonPath('data.permissions', ['clients.create', 'clients.view'])
            ->assertJsonPath('data.is_protected', false)
            ->assertJsonPath('data.users_count', 0);

        $this->assertTrue(Str::isUuid($response->json('data.id')));

        $this->assertDatabaseHas('roles', [
            'name' => 'supervisor',
        ]);
    }

    public function test_role_creation_validates_name_regex_and_uniqueness(): void
    {
        $this->authenticateAsAdmin();

        // Invalid regex
        $this->postJson('/api/v1/roles', ['name' => 'SUPERVISOR'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        // Duplicate name
        $this->postJson('/api/v1/roles', ['name' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_role_creation_validates_permissions_exist(): void
    {
        $this->authenticateAsAdmin();

        $payload = [
            'name' => 'editor',
            'permissions' => ['nonexistent.permission'],
        ];

        $this->postJson('/api/v1/roles', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_role_creation_requires_permission(): void
    {
        $this->authenticateWithPermissions(['roles.view']);

        $this->postJson('/api/v1/roles', [
            'name' => 'tester',
        ])->assertForbidden();
    }

    public function test_role_can_be_retrieved_by_id(): void
    {
        $this->authenticateAsAdmin();

        $role = Role::where('name', 'manager')->firstOrFail();

        $response = $this->getJson("/api/v1/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $role->id)
            ->assertJsonPath('data.name', 'manager');
    }

    public function test_role_returns_404_when_uuid_not_found(): void
    {
        $this->authenticateAsAdmin();

        $fakeUuid = (string) Str::uuid();
        $response = $this->getJson("/api/v1/roles/{$fakeUuid}");

        $response->assertNotFound()
            ->assertJsonPath('message', "Rol no encontrado: {$fakeUuid}");
    }

    public function test_role_returns_404_when_id_is_not_uuid(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/roles/invalid-uuid');

        $response->assertNotFound();
    }

    public function test_role_can_be_updated_and_sync_permissions(): void
    {
        $this->authenticateAsAdmin();

        $role = Role::create([
            'name' => 'support',
            'description' => 'Soporte técnico',
        ]);
        $role->syncPermissions(['clients.view']);

        $payload = [
            'name' => 'support-lead',
            'description' => 'Líder de soporte técnico',
            'permissions' => ['clients.view', 'clients.update'],
        ];

        $response = $this->putJson("/api/v1/roles/{$role->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.name', 'support-lead')
            ->assertJsonPath('data.description', 'Líder de soporte técnico')
            ->assertJsonPath('data.permissions', ['clients.update', 'clients.view']);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'support-lead',
        ]);
    }

    public function test_role_update_validates_uniqueness_ignoring_self(): void
    {
        $this->authenticateAsAdmin();

        $role = Role::create([
            'name' => 'analyst',
            'description' => 'Analista',
        ]);

        $payload = [
            'name' => 'analyst',
            'description' => 'Analista Senior',
        ];

        $this->putJson("/api/v1/roles/{$role->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.description', 'Analista Senior');
    }

    public function test_protected_role_cannot_be_updated(): void
    {
        $this->authenticateAsAdmin();

        $admin = Role::where('name', 'admin')->firstOrFail();

        $payload = [
            'name' => 'admin-renamed',
            'description' => 'Intentando renombrar admin',
        ];

        $response = $this->putJson("/api/v1/roles/{$admin->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'El rol admin es de sistema y no puede modificarse.');
    }

    public function test_privilege_escalation_is_prevented_when_assigning_permissions(): void
    {
        // Custom non-admin user with roles.create and clients.view
        $this->authenticateWithPermissions(['roles.create', 'clients.view']);

        // Attempt to assign permissions.delete which the user does not possess
        $payload = [
            'name' => 'privileged-role',
            'permissions' => ['permissions.delete'],
        ];

        $response = $this->postJson('/api/v1/roles', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permissions']);
    }

    public function test_role_can_be_deleted_when_no_users_assigned(): void
    {
        $this->authenticateAsAdmin();

        $role = Role::create([
            'name' => 'temporary-role',
            'description' => 'Rol temporal para borrar',
        ]);

        $response = $this->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_protected_role_cannot_be_deleted(): void
    {
        $this->authenticateAsAdmin();

        $admin = Role::where('name', 'admin')->firstOrFail();

        $response = $this->deleteJson("/api/v1/roles/{$admin->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'El rol admin es de sistema y no puede modificarse.');
    }

    public function test_role_cannot_be_deleted_when_users_assigned(): void
    {
        $this->authenticateAsAdmin();

        $role = Role::create([
            'name' => 'auditor',
        ]);

        $assignedUser = User::factory()->create();
        $assignedUser->assignRole($role);

        $response = $this->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(409)
            ->assertJsonPath('message', 'El rol tiene 1 usuario(s) asignado(s).');

        $this->assertNotSoftDeleted('roles', [
            'id' => $role->id,
        ]);
    }
}
