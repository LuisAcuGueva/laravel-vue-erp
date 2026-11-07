<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Users;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsersApiTest extends TestCase
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

    public function test_users_can_be_listed_with_pagination_and_filters(): void
    {
        $this->authenticateAsAdmin();

        $managerRole = Role::where('name', 'manager')->first();
        $userRole = Role::where('name', 'user')->first();

        $user1 = User::factory()->create([
            'name' => 'Carlos Gardel',
            'email' => 'carlos@tangomusic.com',
            'status' => User::STATUS_ACTIVE,
        ]);
        $user1->assignRole($managerRole);

        $user2 = User::factory()->create([
            'name' => 'Astor Piazzolla',
            'email' => 'astor@tango.com',
            'status' => User::STATUS_INACTIVE,
        ]);
        $user2->assignRole($userRole);

        // Sin filtros
        $response = $this->getJson('/api/v1/users');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'status',
                        'roles',
                        'last_login_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);

        // Filtro por búsqueda
        $searchResponse = $this->getJson('/api/v1/users?search=Carlos');
        $searchResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'carlos@tangomusic.com');

        // Filtro por rol
        $roleResponse = $this->getJson('/api/v1/users?role=user');
        $roleResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'astor@tango.com');

        // Filtro por status
        $statusResponse = $this->getJson('/api/v1/users?status=inactive');
        $statusResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'inactive');
    }

    public function test_users_can_be_filtered_by_without_employee(): void
    {
        $this->authenticateAsAdmin();

        $linkedUser = User::factory()->create(['name' => 'Linked User']);
        Employee::factory()->withUser($linkedUser)->create();

        $unlinkedUser = User::factory()->create(['name' => 'Unlinked User']);

        $response = $this->getJson('/api/v1/users?without_employee=true');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($unlinkedUser->id));
        $this->assertFalse($ids->contains($linkedUser->id));
    }

    public function test_users_listing_does_not_expose_password_or_remember_token(): void
    {
        $this->authenticateAsAdmin();

        User::factory()->create([
            'name' => 'Secret User',
            'email' => 'secret@example.com',
        ]);

        $response = $this->getJson('/api/v1/users');
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('password', (string) $content);
        $this->assertStringNotContainsString('remember_token', (string) $content);
    }

    public function test_users_listing_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/users');
        $response->assertStatus(401);
    }

    public function test_users_listing_requires_permission(): void
    {
        $this->authenticateWithPermissions([]);

        $response = $this->getJson('/api/v1/users');
        $response->assertStatus(403);
    }

    public function test_user_can_be_created_with_roles(): void
    {
        $this->authenticateAsAdmin();

        $payload = [
            'name' => 'Nuevo Empleado',
            'email' => 'nuevo@example.com',
            'password' => 'secret1234',
            'status' => 'active',
            'roles' => ['manager', 'user'],
        ];

        $response = $this->postJson('/api/v1/users', $payload);
        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Nuevo Empleado')
            ->assertJsonPath('data.email', 'nuevo@example.com')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.roles', ['manager', 'user']);

        $user = User::where('email', 'nuevo@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('secret1234', $user->password));
        $this->assertTrue($user->hasRole('manager'));
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_user_creation_validates_required_fields_and_uniqueness(): void
    {
        $this->authenticateAsAdmin();

        User::factory()->create(['email' => 'existente@example.com']);

        // Payload vacío
        $response = $this->postJson('/api/v1/users', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'roles']);

        // Email duplicado y password corto
        $response = $this->postJson('/api/v1/users', [
            'name' => 'Test',
            'email' => 'existente@example.com',
            'password' => '123',
            'roles' => ['user'],
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_creation_validates_roles_exist(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
            'roles' => ['rol_no_existente'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles.0']);
    }

    public function test_user_creation_requires_permission(): void
    {
        $this->authenticateWithPermissions(['users.view']);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
            'roles' => ['user'],
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_be_retrieved_by_id(): void
    {
        $this->authenticateAsAdmin();

        $user = User::factory()->create([
            'name' => 'Consulta',
            'email' => 'consulta@example.com',
        ]);
        $user->assignRole('user');

        $response = $this->getJson("/api/v1/users/{$user->id}");
        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Consulta')
            ->assertJsonPath('data.roles', ['user']);
    }

    public function test_user_retrieval_returns_404_when_uuid_not_found(): void
    {
        $this->authenticateAsAdmin();

        $nonExistentUuid = (string) Str::uuid();
        $response = $this->getJson("/api/v1/users/{$nonExistentUuid}");
        $response->assertStatus(404);
    }

    public function test_user_retrieval_returns_404_when_id_is_not_uuid(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/users/not-a-valid-uuid');
        $response->assertStatus(404);
    }

    public function test_user_can_be_updated_without_password_retaining_old_password(): void
    {
        $this->authenticateAsAdmin();

        $originalHash = Hash::make('original-password');
        $user = User::factory()->create([
            'name' => 'Antes de editar',
            'email' => 'antes@example.com',
            'password' => $originalHash,
        ]);
        $user->assignRole('user');

        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Después de editar',
            'email' => 'despues@example.com',
            'status' => 'inactive',
            'roles' => ['manager'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Después de editar')
            ->assertJsonPath('data.email', 'despues@example.com')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.roles', ['manager']);

        $fresh = $user->fresh();
        $this->assertEquals($originalHash, $fresh->password);
        $this->assertTrue($fresh->hasRole('manager'));
        $this->assertFalse($fresh->hasRole('user'));
    }

    public function test_user_can_be_updated_with_new_password(): void
    {
        $this->authenticateAsAdmin();

        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'new-strong-password',
        ]);

        $response->assertOk();
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('new-strong-password', $fresh->password));
    }

    public function test_user_update_validates_email_uniqueness_ignoring_self(): void
    {
        $this->authenticateAsAdmin();

        $other = User::factory()->create(['email' => 'otro@example.com']);
        $user = User::factory()->create(['email' => 'mio@example.com']);

        // Modificar con el propio email no falla
        $response1 = $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Mismo email',
            'email' => 'mio@example.com',
        ]);
        $response1->assertOk();

        // Modificar con el email de otro sí falla
        $response2 = $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Intento duplicado',
            'email' => 'otro@example.com',
        ]);
        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_last_admin_role_cannot_be_removed(): void
    {
        $admin = $this->authenticateAsAdmin();

        $response = $this->putJson("/api/v1/users/{$admin->id}", [
            'name' => 'Admin downgrade',
            'email' => $admin->email,
            'roles' => ['user'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No puede quitar el rol admin al último administrador del sistema.');
    }

    public function test_privilege_escalation_is_prevented_when_assigning_roles(): void
    {
        $managerRole = Role::where('name', 'manager')->first();
        $adminRole = Role::where('name', 'admin')->first();

        // Usuario autenticado con rol manager y permiso users.create, pero no admin
        $this->authenticateWithPermissions(['users.create', 'users.view'], $managerRole);

        $targetUser = [
            'name' => 'Escalated User',
            'email' => 'escalated@example.com',
            'password' => 'secret1234',
            'roles' => ['admin'],
        ];

        $response = $this->postJson('/api/v1/users', $targetUser);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles']);
    }

    public function test_user_can_be_deleted_softly_and_revokes_refresh_tokens(): void
    {
        $this->authenticateAsAdmin();

        $user = User::factory()->create();
        $user->assignRole('user');

        $jwtService = app(JwtService::class);
        $jwtService->createTokenPair($user);

        $response = $this->deleteJson("/api/v1/users/{$user->id}");
        $response->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('refresh_tokens', ['user_id' => $user->id]);
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $admin = $this->authenticateAsAdmin();

        $response = $this->deleteJson("/api/v1/users/{$admin->id}");
        $response->assertStatus(422)
            ->assertJsonPath('message', 'No puede eliminar su propia cuenta de usuario.');
    }

    public function test_last_admin_cannot_be_deleted(): void
    {
        $admin = $this->authenticateAsAdmin();

        // Creamos otro usuario con permiso users.delete pero sin rol admin
        $killer = $this->authenticateWithPermissions(['users.delete']);

        $response = $this->deleteJson("/api/v1/users/{$admin->id}");
        $response->assertStatus(422)
            ->assertJsonPath('message', 'No puede eliminar al último administrador del sistema.');
    }

    public function test_user_deletion_requires_permission(): void
    {
        $this->authenticateWithPermissions(['users.view']);

        $other = User::factory()->create();

        $response = $this->deleteJson("/api/v1/users/{$other->id}");
        $response->assertStatus(403);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactivo@example.com',
            'password' => Hash::make('password123'),
            'status' => User::STATUS_INACTIVE,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactivo@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Usuario inactivo.');
    }

    public function test_token_version_increment_invalidates_previous_access_tokens(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->assignRole('user');

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);

        // Con versión actual, accede a /auth/me
        $okResponse = $this->withHeader('Authorization', 'Bearer '.$pair['access_token'])
            ->getJson('/api/auth/me');
        $okResponse->assertOk();

        // Incrementamos la versión del token
        $user->increment('token_version');

        // Con el token previo (con tv antigua), es rechazado con 401
        $rejectedResponse = $this->withHeader('Authorization', 'Bearer '.$pair['access_token'])
            ->getJson('/api/auth/me');
        $rejectedResponse->assertStatus(401)
            ->assertJsonPath('message', 'Token revocado o versión no válida.');
    }
}
