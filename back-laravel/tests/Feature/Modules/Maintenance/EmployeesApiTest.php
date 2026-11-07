<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Maintenance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Maintenance\Models\Area;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use App\Support\Identity\DocumentType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class EmployeesApiTest extends TestCase
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

    public function test_employees_can_be_listed_with_pagination_and_filters(): void
    {
        $this->authenticateWithPermissions(['employees.view']);

        $branch = Branch::factory()->create();
        $area = Area::factory()->create();

        $emp1 = Employee::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'document_type' => DocumentType::Dni,
            'document_number' => '11112222',
            'branch_id' => $branch->id,
            'area_id' => $area->id,
            'is_active' => true,
        ]);

        $emp2 = Employee::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Gomez',
            'document_type' => DocumentType::Dni,
            'document_number' => '33334444',
            'is_active' => false,
        ]);

        // List all
        $response = $this->getJson('/api/v1/employees');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'document_type',
                        'document_number',
                        'first_name',
                        'last_name',
                        'full_name',
                        'email',
                        'phone',
                        'position',
                        'is_active',
                        'area',
                        'branch',
                        'user',
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        // Filter by branch
        $responseBranch = $this->getJson("/api/v1/employees?branch_id={$branch->id}");
        $responseBranch->assertOk();
        $dataBranch = $responseBranch->json('data');
        $this->assertCount(1, $dataBranch);
        $this->assertSame($emp1->id, $dataBranch[0]['id']);

        // Filter by search
        $responseSearch = $this->getJson('/api/v1/employees?search=Maria');
        $responseSearch->assertOk();
        $dataSearch = $responseSearch->json('data');
        $this->assertCount(1, $dataSearch);
        $this->assertSame($emp2->id, $dataSearch[0]['id']);

        // Filter by is_active
        $responseActive = $this->getJson('/api/v1/employees?is_active=false');
        $responseActive->assertOk();
        $dataActive = $responseActive->json('data');
        $this->assertCount(1, $dataActive);
        $this->assertSame($emp2->id, $dataActive[0]['id']);
    }

    public function test_employee_can_be_created_with_title_case_names(): void
    {
        $this->authenticateWithPermissions(['employees.create', 'employees.view']);

        $branch = Branch::factory()->create();
        $area = Area::factory()->create();

        $payload = [
            'document_type' => 'DNI',
            'document_number' => '87654321',
            'first_name' => 'carlos alberto',
            'last_name' => 'sanchez flores',
            'email' => 'carlos.sanchez@example.com',
            'phone' => '999888777',
            'position' => 'Vendedor',
            'area_id' => $area->id,
            'branch_id' => $branch->id,
            'birth_date' => '1990-05-15',
            'hire_date' => '2025-01-10',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/employees', $payload);
        $response->assertCreated()
            ->assertJsonPath('data.first_name', 'Carlos Alberto')
            ->assertJsonPath('data.last_name', 'Sanchez Flores')
            ->assertJsonPath('data.full_name', 'Carlos Alberto Sanchez Flores')
            ->assertJsonPath('data.document_number', '87654321')
            ->assertJsonPath('data.area.id', $area->id)
            ->assertJsonPath('data.branch.id', $branch->id);

        $this->assertDatabaseHas('employees', [
            'document_number' => '87654321',
            'first_name' => 'Carlos Alberto',
            'last_name' => 'Sanchez Flores',
        ]);
    }

    public function test_employee_can_be_shown_and_updated(): void
    {
        $this->authenticateWithPermissions(['employees.view', 'employees.update']);

        $employee = Employee::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Torres',
        ]);

        $showResponse = $this->getJson("/api/v1/employees/{$employee->id}");
        $showResponse->assertOk()
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.first_name', 'Ana');

        $updateResponse = $this->putJson("/api/v1/employees/{$employee->id}", [
            'document_type' => $employee->document_type->value,
            'document_number' => $employee->document_number,
            'first_name' => 'Ana Maria',
            'last_name' => 'Torres Vega',
            'position' => 'Supervisora',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.first_name', 'Ana Maria')
            ->assertJsonPath('data.last_name', 'Torres Vega')
            ->assertJsonPath('data.position', 'Supervisora');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'Ana Maria',
            'last_name' => 'Torres Vega',
        ]);
    }

    public function test_employee_can_be_soft_deleted(): void
    {
        $this->authenticateWithPermissions(['employees.delete']);

        $employee = Employee::factory()->create();

        $response = $this->deleteJson("/api/v1/employees/{$employee->id}");
        $response->assertNoContent();

        $this->assertSoftDeleted('employees', [
            'id' => $employee->id,
        ]);
    }

    public function test_duplicate_document_returns_422(): void
    {
        $this->authenticateWithPermissions(['employees.create']);

        Employee::factory()->create([
            'document_type' => DocumentType::Dni,
            'document_number' => '44556677',
        ]);

        $response = $this->postJson('/api/v1/employees', [
            'document_type' => 'DNI',
            'document_number' => '44556677',
            'first_name' => 'Pedro',
            'last_name' => 'Lopez',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_number']);
    }

    public function test_soft_deleted_employee_document_can_be_reused(): void
    {
        $this->authenticateWithPermissions(['employees.create', 'employees.delete']);

        $employee = Employee::factory()->create([
            'document_type' => DocumentType::Dni,
            'document_number' => '44556677',
        ]);
        $employee->delete();

        $response = $this->postJson('/api/v1/employees', [
            'document_type' => 'DNI',
            'document_number' => '44556677',
            'first_name' => 'Nuevo Pedro',
            'last_name' => 'Lopez',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.document_number', '44556677');
    }

    public function test_invalid_dni_length_returns_422(): void
    {
        $this->authenticateWithPermissions(['employees.create']);

        $response = $this->postJson('/api/v1/employees', [
            'document_type' => 'DNI',
            'document_number' => '1234567', // 7 digits instead of 8
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_number']);
    }

    public function test_inactive_area_or_branch_returns_422(): void
    {
        $this->authenticateWithPermissions(['employees.create']);

        $inactiveArea = Area::factory()->inactive()->create();
        $inactiveBranch = Branch::factory()->inactive()->create();

        $responseArea = $this->postJson('/api/v1/employees', [
            'document_type' => 'DNI',
            'document_number' => '12345678',
            'first_name' => 'Test',
            'last_name' => 'User',
            'area_id' => $inactiveArea->id,
        ]);
        $responseArea->assertStatus(422)->assertJsonValidationErrors(['area_id']);

        $responseBranch = $this->postJson('/api/v1/employees', [
            'document_type' => 'DNI',
            'document_number' => '12345678',
            'first_name' => 'Test',
            'last_name' => 'User',
            'branch_id' => $inactiveBranch->id,
        ]);
        $responseBranch->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
    }

    public function test_employee_options_returns_only_active_untrashed(): void
    {
        // Options only requires authentication, no special permission
        $this->authenticateWithPermissions([]);

        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $activeA = Employee::factory()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Activo A',
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);

        $activeB = Employee::factory()->create([
            'first_name' => 'Beatriz',
            'last_name' => 'Activo B',
            'branch_id' => $branchB->id,
            'is_active' => true,
        ]);

        $inactive = Employee::factory()->inactive()->create([
            'branch_id' => $branchA->id,
        ]);

        $deleted = Employee::factory()->create([
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);
        $deleted->delete();

        // All active
        $response = $this->getJson('/api/v1/employees/options');
        $response->assertOk();
        $data = $response->json('data');
        $ids = array_column($data, 'id');
        $this->assertContains($activeA->id, $ids);
        $this->assertContains($activeB->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($deleted->id, $ids);

        // Filter by branchA
        $responseBranch = $this->getJson("/api/v1/employees/options?branch_id={$branchA->id}");
        $responseBranch->assertOk();
        $branchIds = array_column($responseBranch->json('data'), 'id');
        $this->assertContains($activeA->id, $branchIds);
        $this->assertNotContains($activeB->id, $branchIds);
    }

    public function test_updating_employee_name_synchronizes_user_name(): void
    {
        $this->authenticateWithPermissions(['employees.update']);

        $user = User::factory()->create(['name' => 'Original Name']);
        $employee = Employee::factory()->create([
            'first_name' => 'Original',
            'last_name' => 'Name',
            'user_id' => $user->id,
        ]);

        $response = $this->putJson("/api/v1/employees/{$employee->id}", [
            'document_type' => $employee->document_type->value,
            'document_number' => $employee->document_number,
            'first_name' => 'Renombrado',
            'last_name' => 'Actualizado',
        ]);

        $response->assertOk();

        $this->assertSame('Renombrado Actualizado', $user->fresh()?->name);
    }

    public function test_deleting_employee_unlinks_user_without_deleting_it(): void
    {
        $this->authenticateWithPermissions(['employees.delete']);

        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/v1/employees/{$employee->id}");
        $response->assertNoContent();

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_delete_branch_with_assigned_employees(): void
    {
        $this->authenticateWithPermissions(['branches.delete']);

        $branch = Branch::factory()->create(['is_main' => false]);
        Employee::factory()->create(['branch_id' => $branch->id]);

        $response = $this->deleteJson("/api/v1/branches/{$branch->id}");
        $response->assertStatus(422)
            ->assertJsonPath('message', 'La sede tiene empleados asignados.');
    }

    public function test_unauthorized_user_cannot_access_employee_endpoints(): void
    {
        $this->authenticateWithPermissions([]);

        $employee = Employee::factory()->create();

        $this->getJson('/api/v1/employees')->assertForbidden();
        $this->postJson('/api/v1/employees', [])->assertForbidden();
        $this->getJson("/api/v1/employees/{$employee->id}")->assertForbidden();
        $this->putJson("/api/v1/employees/{$employee->id}", [])->assertForbidden();
        $this->deleteJson("/api/v1/employees/{$employee->id}")->assertForbidden();
    }
}
