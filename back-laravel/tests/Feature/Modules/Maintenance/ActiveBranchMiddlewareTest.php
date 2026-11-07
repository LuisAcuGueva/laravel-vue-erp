<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Maintenance;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\TestCase;

class ActiveBranchMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();

        Route::middleware(['jwt', 'branch'])->get('/api/test/active-branch', function () {
            return response()->json([
                'id' => app(ActiveBranch::class)->id(),
            ]);
        });
    }

    private function authenticateUser(User $user): void
    {
        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);
    }

    private function authenticateAsAdmin(): User
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $adminRole->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        $this->authenticateUser($user);

        return $user;
    }

    public function test_resolves_active_branch_from_valid_header(): void
    {
        $b1 = Branch::factory()->main()->create();
        $b2 = Branch::factory()->create();

        $user = User::factory()->create();
        $user->branches()->attach($b1->id, ['is_default' => true]);
        $user->branches()->attach($b2->id, ['is_default' => false]);

        $this->authenticateUser($user);

        $response = $this->withHeader('X-Branch-Id', $b2->id)
            ->getJson('/api/test/active-branch');

        $response->assertOk()
            ->assertJsonPath('id', $b2->id);
    }

    public function test_resolves_default_branch_when_header_is_missing(): void
    {
        $b1 = Branch::factory()->main()->create();
        $b2 = Branch::factory()->create();

        $user = User::factory()->create();
        $user->branches()->attach($b1->id, ['is_default' => false]);
        $user->branches()->attach($b2->id, ['is_default' => true]);

        $this->authenticateUser($user);

        $response = $this->getJson('/api/test/active-branch');

        $response->assertOk()
            ->assertJsonPath('id', $b2->id);
    }

    public function test_returns_403_when_header_is_invalid_uuid_or_not_assigned(): void
    {
        $b1 = Branch::factory()->main()->create();
        $bUnassigned = Branch::factory()->create();

        $user = User::factory()->create();
        $user->branches()->attach($b1->id, ['is_default' => true]);

        $this->authenticateUser($user);

        // Non-UUID
        $resInvalid = $this->withHeader('X-Branch-Id', 'not-a-uuid')
            ->getJson('/api/test/active-branch');
        $resInvalid->assertStatus(403)
            ->assertJsonPath('message', 'No tiene acceso a la sede seleccionada.');

        // Unassigned UUID
        $resUnassigned = $this->withHeader('X-Branch-Id', $bUnassigned->id)
            ->getJson('/api/test/active-branch');
        $resUnassigned->assertStatus(403)
            ->assertJsonPath('message', 'No tiene acceso a la sede seleccionada.');
    }

    public function test_returns_409_when_user_has_no_branches_assigned(): void
    {
        Branch::factory()->main()->create();
        $user = User::factory()->create();

        $this->authenticateUser($user);

        $response = $this->getJson('/api/test/active-branch');

        $response->assertStatus(409)
            ->assertJsonPath('message', 'El usuario no tiene sedes asignadas.');
    }

    public function test_admin_can_access_any_active_branch_via_header(): void
    {
        $b1 = Branch::factory()->main()->create();
        $b2 = Branch::factory()->create();

        $this->authenticateAsAdmin();

        $response = $this->withHeader('X-Branch-Id', $b2->id)
            ->getJson('/api/test/active-branch');

        $response->assertOk()
            ->assertJsonPath('id', $b2->id);
    }

    public function test_admin_defaults_to_main_branch_when_header_is_missing(): void
    {
        $main = Branch::factory()->main()->create();
        Branch::factory()->create(['is_main' => false]);

        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/test/active-branch');

        $response->assertOk()
            ->assertJsonPath('id', $main->id);
    }

    public function test_active_branch_get_throws_logic_exception_outside_request(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ActiveBranch no ha sido resuelta para esta petición.');

        $activeBranch = new ActiveBranch;
        $activeBranch->get();
    }
}
