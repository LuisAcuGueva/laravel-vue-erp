<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Maintenance;

use App\Modules\Audit\Models\Activity;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBranchesApiTest extends TestCase
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

    private function authenticateUser(User $user): void
    {
        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);
    }

    public function test_user_branches_can_be_synced_and_sets_default(): void
    {
        $this->authenticateAsAdmin();

        $branch1 = Branch::factory()->main()->create(['name' => 'Sede 1']);
        $branch2 = Branch::factory()->create(['name' => 'Sede 2']);
        $targetUser = User::factory()->create();

        $response = $this->putJson("/api/v1/users/{$targetUser->id}/branches", [
            'branch_ids' => [$branch1->id, $branch2->id],
            'default_branch_id' => $branch2->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $targetUser->id)
            ->assertJsonCount(2, 'data.branches');

        $branches = $response->json('data.branches');
        $defaultFound = false;
        foreach ($branches as $b) {
            if ($b['id'] === $branch2->id) {
                $this->assertTrue($b['is_default']);
                $defaultFound = true;
            } else {
                $this->assertFalse($b['is_default']);
            }
        }
        $this->assertTrue($defaultFound);

        // Check activity log
        $activity = Activity::where('subject_type', 'user')
            ->where('subject_id', $targetUser->id)
            ->where('description', 'branches_synced')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($branch2->id, $activity->properties['default']);
    }

    public function test_default_branch_must_be_in_branch_ids(): void
    {
        $this->authenticateAsAdmin();

        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();
        $targetUser = User::factory()->create();

        $response = $this->putJson("/api/v1/users/{$targetUser->id}/branches", [
            'branch_ids' => [$branch1->id],
            'default_branch_id' => $branch2->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['default_branch_id']);
    }

    public function test_cannot_assign_inactive_or_deleted_branch(): void
    {
        $this->authenticateAsAdmin();

        $inactiveBranch = Branch::factory()->inactive()->create();
        $targetUser = User::factory()->create();

        $response = $this->putJson("/api/v1/users/{$targetUser->id}/branches", [
            'branch_ids' => [$inactiveBranch->id],
            'default_branch_id' => $inactiveBranch->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_ids.0']);
    }

    public function test_auth_me_returns_accessible_branches(): void
    {
        $branch1 = Branch::factory()->main()->create(['name' => 'Sede A']);
        $branch2 = Branch::factory()->create(['name' => 'Sede B']);
        $branch3 = Branch::factory()->create(['name' => 'Sede C']);

        $user = User::factory()->create();
        $user->branches()->attach($branch1->id, ['is_default' => true]);
        $user->branches()->attach($branch2->id, ['is_default' => false]);

        $this->authenticateUser($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonCount(2, 'branches')
            ->assertJsonPath('branches.0.id', $branch1->id)
            ->assertJsonPath('branches.0.is_default', true)
            ->assertJsonPath('branches.1.id', $branch2->id)
            ->assertJsonPath('branches.1.is_default', false);
    }

    public function test_auth_me_returns_all_active_branches_for_admin(): void
    {
        Branch::factory()->main()->create(['name' => 'Sede 1']);
        Branch::factory()->create(['name' => 'Sede 2']);
        Branch::factory()->inactive()->create(['name' => 'Sede 3 Inactiva']);

        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonCount(2, 'branches'); // Solo las 2 activas
    }
}
