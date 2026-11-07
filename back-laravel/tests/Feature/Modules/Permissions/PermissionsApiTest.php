<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Permissions;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PermissionsApiTest extends TestCase
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

    private function authenticateWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
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

    public function test_permissions_can_be_listed_with_pagination_and_filters(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/permissions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'module', 'description', 'roles_count', 'created_at', 'updated_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        // Filter by module
        $moduleResponse = $this->getJson('/api/v1/permissions?module=users');
        $moduleResponse->assertOk();
        foreach ($moduleResponse->json('data') as $item) {
            $this->assertSame('users', $item['module']);
        }

        // Search filter
        $searchResponse = $this->getJson('/api/v1/permissions?search=users.view');
        $searchResponse->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'users.view');
    }

    public function test_permissions_listing_validates_per_page_range(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/permissions?per_page=101');
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $responseZero = $this->getJson('/api/v1/permissions?per_page=0');
        $responseZero->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_permissions_listing_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/permissions');
        $response->assertUnauthorized();
    }

    public function test_permissions_listing_requires_permission(): void
    {
        $this->authenticateWithPermissions(['clients.view']);

        $response = $this->getJson('/api/v1/permissions');
        $response->assertForbidden();
    }

    public function test_permission_can_be_created(): void
    {
        $this->authenticateAsAdmin();

        $payload = [
            'name' => 'Ver facturas',
            'slug' => 'invoices.view',
            'module' => 'invoices',
            'description' => 'Permite listar facturas',
        ];

        $response = $this->postJson('/api/v1/permissions', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Ver facturas')
            ->assertJsonPath('data.slug', 'invoices.view')
            ->assertJsonPath('data.module', 'invoices')
            ->assertJsonPath('data.description', 'Permite listar facturas')
            ->assertJsonMissing(['guard_name']);

        $this->assertTrue(Str::isUuid($response->json('data.id')));

        $this->assertDatabaseHas('permissions', [
            'slug' => 'invoices.view',
            'module' => 'invoices',
        ]);

        // Verifies the permission is NOT automatically assigned to the admin role
        $admin = Role::where('name', 'admin')->firstOrFail();
        $this->assertFalse($admin->hasPermissionTo('invoices.view'));
    }

    public function test_permission_creation_derives_module_from_slug(): void
    {
        $this->authenticateAsAdmin();

        $payload = [
            'name' => 'Crear facturas',
            'slug' => 'invoices.create',
        ];

        $response = $this->postJson('/api/v1/permissions', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.module', 'invoices');

        $this->assertDatabaseHas('permissions', [
            'slug' => 'invoices.create',
            'module' => 'invoices',
        ]);
    }

    public function test_permission_creation_validates_slug_format_and_uniqueness(): void
    {
        $this->authenticateAsAdmin();

        // Invalid regex
        $invalidPayload = [
            'name' => 'Permiso inválido',
            'slug' => 'INVOICES_VIEW',
        ];
        $this->postJson('/api/v1/permissions', $invalidPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);

        // Duplicate slug
        $duplicatePayload = [
            'name' => 'Ver usuarios duplicado',
            'slug' => 'users.view',
        ];
        $this->postJson('/api/v1/permissions', $duplicatePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_permission_creation_validates_module_matches_slug_prefix(): void
    {
        $this->authenticateAsAdmin();

        $payload = [
            'name' => 'Módulo no coincidente',
            'slug' => 'invoices.export',
            'module' => 'reports',
        ];

        $this->postJson('/api/v1/permissions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['module']);
    }

    public function test_permission_creation_requires_permission(): void
    {
        $this->authenticateWithPermissions(['permissions.view']);

        $response = $this->postJson('/api/v1/permissions', [
            'name' => 'Crear facturas',
            'slug' => 'invoices.create',
        ]);

        $response->assertForbidden();
    }

    public function test_permission_can_be_retrieved_by_id(): void
    {
        $this->authenticateAsAdmin();

        $permission = Permission::where('slug', 'users.view')->firstOrFail();

        $response = $this->getJson("/api/v1/permissions/{$permission->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $permission->id)
            ->assertJsonPath('data.slug', 'users.view');
    }

    public function test_permission_returns_404_when_uuid_not_found(): void
    {
        $this->authenticateAsAdmin();

        $fakeUuid = (string) Str::uuid();
        $response = $this->getJson("/api/v1/permissions/{$fakeUuid}");

        $response->assertNotFound()
            ->assertJsonPath('message', "Permiso no encontrado: {$fakeUuid}");
    }

    public function test_permission_returns_404_when_id_is_not_uuid(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/v1/permissions/invalid-uuid');

        $response->assertNotFound();
    }

    public function test_permission_can_be_updated(): void
    {
        $this->authenticateAsAdmin();

        $permission = Permission::create([
            'name' => 'Permiso Temporal',
            'slug' => 'temp.action',
            'module' => 'temp',
            'description' => 'Descripción vieja',
        ]);

        $payload = [
            'name' => 'Permiso Modificado',
            'slug' => 'temp.action',
            'module' => 'temp',
            'description' => 'Descripción nueva',
        ];

        $response = $this->putJson("/api/v1/permissions/{$permission->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Permiso Modificado')
            ->assertJsonPath('data.description', 'Descripción nueva');

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'name' => 'Permiso Modificado',
            'description' => 'Descripción nueva',
        ]);
    }

    public function test_permission_can_be_deleted_when_not_in_use(): void
    {
        $this->authenticateAsAdmin();

        $permission = Permission::create([
            'name' => 'Permiso Libre',
            'slug' => 'free.action',
            'module' => 'free',
        ]);

        // Remove from admin if auto-attached
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->revokePermissionTo($permission);
        }

        $response = $this->deleteJson("/api/v1/permissions/{$permission->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('permissions', [
            'id' => $permission->id,
        ]);
    }

    public function test_permission_cannot_be_deleted_when_assigned_to_roles(): void
    {
        $this->authenticateAsAdmin();

        $permission = Permission::where('slug', 'users.view')->firstOrFail();

        $response = $this->deleteJson("/api/v1/permissions/{$permission->id}");

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertNotSoftDeleted('permissions', [
            'id' => $permission->id,
        ]);
    }
}
