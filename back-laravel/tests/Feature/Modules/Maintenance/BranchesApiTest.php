<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Maintenance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class BranchesApiTest extends TestCase
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

    public function test_branches_can_be_listed_with_pagination_and_filters(): void
    {
        $this->authenticateAsAdmin();

        Branch::factory()->create(['code' => 'BR-001', 'name' => 'Sede Norte', 'is_active' => true]);
        Branch::factory()->create(['code' => 'BR-002', 'name' => 'Sede Sur', 'is_active' => false]);

        $response = $this->getJson('/api/v1/branches?search=Norte');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'BR-001');

        $filterActive = $this->getJson('/api/v1/branches?is_active=0');
        $filterActive->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'BR-002');
    }

    public function test_first_branch_created_becomes_main(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/v1/branches', [
            'code' => 'SED-01',
            'name' => 'Primera Sede',
            'is_main' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_main', true);

        $this->assertDatabaseHas('branches', [
            'code' => 'SED-01',
            'is_main' => true,
        ]);
    }

    public function test_branch_can_be_created_retrieved_updated_and_deleted(): void
    {
        $this->authenticateAsAdmin();

        $main = Branch::factory()->main()->create();

        // Create secondary branch
        $createResponse = $this->postJson('/api/v1/branches', [
            'code' => 'sed-02',
            'name' => 'Segunda Sede',
            'address' => 'Av. Los Olivos 456',
            'ubigeo' => '150102',
            'phone' => '999888777',
            'email' => 'olivos@empresa.com',
            'is_active' => true,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.code', 'SED-02')
            ->assertJsonPath('data.name', 'Segunda Sede')
            ->assertJsonPath('data.is_main', false);

        $branchId = $createResponse->json('data.id');

        // Show
        $showResponse = $this->getJson("/api/v1/branches/{$branchId}");
        $showResponse->assertOk()
            ->assertJsonPath('data.id', $branchId)
            ->assertJsonPath('data.code', 'SED-02');

        // Update
        $updateResponse = $this->putJson("/api/v1/branches/{$branchId}", [
            'code' => 'SED-02-UPD',
            'name' => 'Sede Los Olivos Modificada',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.code', 'SED-02-UPD')
            ->assertJsonPath('data.name', 'Sede Los Olivos Modificada');

        // Delete
        $deleteResponse = $this->deleteJson("/api/v1/branches/{$branchId}");
        $deleteResponse->assertNoContent();

        $this->assertSoftDeleted('branches', ['id' => $branchId]);
    }

    public function test_unknown_ubigeo_returns_422_on_create_and_update(): void
    {
        $this->authenticateAsAdmin();

        $this->postJson('/api/v1/branches', [
            'code' => 'SED-09',
            'name' => 'Sede Ubigeo',
            'ubigeo' => '999999',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.ubigeo.0', 'El ubigeo seleccionado no es válido.');

        $branch = Branch::factory()->create(['ubigeo' => '150101']);

        $this->putJson("/api/v1/branches/{$branch->id}", [
            'code' => $branch->code,
            'name' => $branch->name,
            'ubigeo' => '999999',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ubigeo']);

        $this->putJson("/api/v1/branches/{$branch->id}", [
            'code' => $branch->code,
            'name' => $branch->name,
            'ubigeo' => '150122',
        ])->assertOk()
            ->assertJsonPath('data.ubigeo', '150122');
    }

    public function test_duplicate_code_or_name_returns_422(): void
    {
        $this->authenticateAsAdmin();

        Branch::factory()->create([
            'code' => 'CODE-DUP',
            'name' => 'Sede Existente',
        ]);

        $res1 = $this->postJson('/api/v1/branches', [
            'code' => 'code-dup',
            'name' => 'Otra Sede',
        ]);
        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $res2 = $this->postJson('/api/v1/branches', [
            'code' => 'OTHER-01',
            'name' => 'Sede Existente',
        ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_reuse_code_of_soft_deleted_branch(): void
    {
        $this->authenticateAsAdmin();

        $branch = Branch::factory()->create([
            'code' => 'REUSED',
            'name' => 'Sede a Borrar',
            'is_main' => false,
        ]);
        Branch::factory()->main()->create();

        $branch->delete();

        $response = $this->postJson('/api/v1/branches', [
            'code' => 'REUSED',
            'name' => 'Nueva Sede con Mismo Código',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'REUSED');
    }

    public function test_marking_another_branch_as_main_unmarks_previous_main(): void
    {
        $this->authenticateAsAdmin();

        $first = Branch::factory()->main()->create(['name' => 'Sede 1']);
        $second = Branch::factory()->create(['name' => 'Sede 2', 'is_main' => false]);

        $this->assertTrue($first->fresh()->is_main);
        $this->assertFalse($second->fresh()->is_main);

        $response = $this->putJson("/api/v1/branches/{$second->id}", [
            'code' => $second->code,
            'name' => $second->name,
            'is_main' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_main', true);

        $this->assertFalse($first->fresh()->is_main);
        $this->assertTrue($second->fresh()->is_main);
    }

    public function test_cannot_unmark_main_branch_directly(): void
    {
        $this->authenticateAsAdmin();

        $main = Branch::factory()->main()->create();

        $response = $this->putJson("/api/v1/branches/{$main->id}", [
            'code' => $main->code,
            'name' => $main->name,
            'is_main' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.branch.0', 'Debe existir una sede principal; marque otra sede como principal.');
    }

    public function test_cannot_deactivate_main_branch(): void
    {
        $this->authenticateAsAdmin();

        $main = Branch::factory()->main()->create();

        $response = $this->putJson("/api/v1/branches/{$main->id}", [
            'code' => $main->code,
            'name' => $main->name,
            'is_active' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.branch.0', 'No se puede desactivar la sede principal.');
    }

    public function test_cannot_delete_main_branch(): void
    {
        $this->authenticateAsAdmin();

        $main = Branch::factory()->main()->create();

        $response = $this->deleteJson("/api/v1/branches/{$main->id}");

        $response->assertStatus(422)
            ->assertJsonPath('errors.branch.0', 'No se puede eliminar la sede principal.');
    }

    public function test_cannot_delete_branch_with_assigned_users(): void
    {
        $this->authenticateAsAdmin();

        Branch::factory()->main()->create();
        $branch = Branch::factory()->create(['is_main' => false]);
        $user = User::factory()->create();

        $user->branches()->attach($branch->id, ['is_default' => true]);

        $response = $this->deleteJson("/api/v1/branches/{$branch->id}");

        $response->assertStatus(422)
            ->assertJsonPath('errors.branch.0', 'La sede tiene usuarios asignados.');
    }

    public function test_branches_options_returns_only_active_branches(): void
    {
        $this->authenticateWithPermissions(['branches.view']);

        Branch::factory()->create(['name' => 'Sede Activa', 'is_active' => true]);
        Branch::factory()->create(['name' => 'Sede Inactiva', 'is_active' => false]);

        $response = $this->getJson('/api/v1/branches/options');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sede Activa');
    }

    public function test_permissions_are_enforced_on_branch_routes(): void
    {
        $this->authenticateWithPermissions(['branches.view']);

        $branch = Branch::factory()->create();

        $this->getJson('/api/v1/branches')->assertOk();
        $this->getJson("/api/v1/branches/{$branch->id}")->assertOk();

        // No create permission
        $this->postJson('/api/v1/branches', [
            'code' => 'NEW-CODE',
            'name' => 'Nueva',
        ])->assertForbidden();

        // No update permission
        $this->putJson("/api/v1/branches/{$branch->id}", [
            'code' => $branch->code,
            'name' => 'Modificada',
        ])->assertForbidden();

        // No delete permission
        $this->deleteJson("/api/v1/branches/{$branch->id}")->assertForbidden();
    }
}
