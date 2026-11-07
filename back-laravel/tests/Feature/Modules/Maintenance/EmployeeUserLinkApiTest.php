<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Maintenance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class EmployeeUserLinkApiTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        $this->seedUbigeoFixture();
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
            $perm = Permission::where('slug', $slug)->firstOrFail();
            $user->givePermissionTo($perm);
        }

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return $user;
    }

    public function test_can_create_user_for_employee_with_auto_branch_assignment(): void
    {
        $this->authenticateAsAdmin();

        $branch = Branch::factory()->create();
        $employee = Employee::factory()->create([
            'first_name' => 'Mario',
            'last_name' => 'Vargas',
            'branch_id' => $branch->id,
            'user_id' => null,
        ]);

        $payload = [
            'email' => 'mario.vargas@empresa.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['user'],
        ];

        $response = $this->postJson("/api/v1/employees/{$employee->id}/user", $payload);
        $response->assertCreated()
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.user.email', 'mario.vargas@empresa.com')
            ->assertJsonPath('data.user.status', 'active');

        $createdUser = User::where('email', 'mario.vargas@empresa.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertSame('Mario Vargas', $createdUser->name);
        $this->assertTrue($createdUser->hasRole('user'));

        // Branch was automatically assigned and marked as default
        $this->assertTrue($createdUser->branches()->where('branches.id', $branch->id)->exists());
        $this->assertSame($branch->id, $createdUser->defaultBranch()?->id);

        // Employee was linked
        $this->assertSame($createdUser->id, $employee->fresh()?->user_id);
    }

    public function test_cannot_create_user_if_employee_already_has_user(): void
    {
        $this->authenticateAsAdmin();

        $existingUser = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $existingUser->id]);

        $response = $this->postJson("/api/v1/employees/{$employee->id}/user", [
            'email' => 'nuevo@empresa.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['user'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'El empleado ya tiene un usuario vinculado.');
    }

    public function test_cannot_create_user_without_users_create_permission(): void
    {
        // Only employees.update, no users.create
        $this->authenticateWithPermissions(['employees.update']);

        $employee = Employee::factory()->create(['user_id' => null]);

        $response = $this->postJson("/api/v1/employees/{$employee->id}/user", [
            'email' => 'nuevo@empresa.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['user'],
        ]);

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_escalate_roles_when_creating_employee_user(): void
    {
        // User with employees.update and users.create, but not admin
        $this->authenticateWithPermissions(['employees.update', 'users.create']);

        $employee = Employee::factory()->create(['user_id' => null]);

        $response = $this->postJson("/api/v1/employees/{$employee->id}/user", [
            'email' => 'admin.escalado@empresa.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['admin'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles']);
    }

    public function test_can_link_existing_user_to_employee(): void
    {
        $this->authenticateWithPermissions(['employees.update']);

        $user = User::factory()->create(['name' => 'Nombre Antiguo']);
        $employee = Employee::factory()->create([
            'first_name' => 'Roberto',
            'last_name' => 'Gomez Bolaños',
            'user_id' => null,
        ]);

        $response = $this->putJson("/api/v1/employees/{$employee->id}/user", [
            'user_id' => $user->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertSame($user->id, $employee->fresh()?->user_id);
        $this->assertSame('Roberto Gomez Bolaños', $user->fresh()?->name);
    }

    public function test_cannot_link_user_already_linked_to_another_employee(): void
    {
        $this->authenticateWithPermissions(['employees.update']);

        $user = User::factory()->create();
        $employeeA = Employee::factory()->create([
            'first_name' => 'Luis',
            'last_name' => 'Primero',
            'user_id' => $user->id,
        ]);

        $employeeB = Employee::factory()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Segundo',
            'user_id' => null,
        ]);

        $response = $this->putJson("/api/v1/employees/{$employeeB->id}/user", [
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', "El usuario ya está vinculado al empleado {$employeeA->full_name}.");
    }

    public function test_can_unlink_user_from_employee(): void
    {
        $this->authenticateWithPermissions(['employees.update']);

        $user = User::factory()->create(['status' => 'active']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/v1/employees/{$employee->id}/user");
        $response->assertOk()
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.user', null);

        $this->assertNull($employee->fresh()?->user_id);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'active',
            'deleted_at' => null,
        ]);
    }
}
