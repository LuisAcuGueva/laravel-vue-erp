<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Modules\Maintenance\Models\Branch;
use App\Modules\Permissions\Models\Permission;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use App\Support\Branch\BranchAccessGuard;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAccessGuardTest extends TestCase
{
    use RefreshDatabase;

    private BranchAccessGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        $this->guard = new BranchAccessGuard;
    }

    public function test_accessible_branch_does_not_throw(): void
    {
        $b1 = Branch::factory()->main()->create();
        $b2 = Branch::factory()->create();

        $user = User::factory()->create();
        $user->branches()->attach([$b1->id, $b2->id]);

        $this->guard->assertAccessible($user, $b1->id);
        $this->guard->assertAccessible($user, $b2->id);

        $this->assertTrue(true);
    }

    public function test_unaccessible_or_null_branch_throws_model_not_found(): void
    {
        $bAssigned = Branch::factory()->main()->create();
        $bUnassigned = Branch::factory()->create();

        $user = User::factory()->create();
        $user->branches()->attach($bAssigned->id);

        $this->expectException(ModelNotFoundException::class);
        $this->guard->assertAccessible($user, $bUnassigned->id);
    }

    public function test_null_branch_throws_model_not_found(): void
    {
        $user = User::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        $this->guard->assertAccessible($user, null);
    }

    public function test_admin_can_access_all_branches_even_inactive(): void
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $adminRole->syncPermissions(Permission::all());

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $activeBranch = Branch::factory()->main()->create();
        $inactiveBranch = Branch::factory()->inactive()->create();

        $this->guard->assertAccessible($admin, $activeBranch->id);
        $this->guard->assertAccessible($admin, $inactiveBranch->id);

        $this->assertTrue(true);
    }

    public function test_scope_query_filters_for_regular_user_and_unrestricted_for_admin(): void
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $adminRole->syncPermissions(Permission::all());

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $user = User::factory()->create();

        $b1 = Branch::factory()->main()->create();
        $b2 = Branch::factory()->create();
        $b3 = Branch::factory()->create();

        $user->branches()->attach([$b1->id, $b2->id]);

        // Regular user: only b1 and b2
        $userQuery = $this->guard->scopeQuery(Branch::query(), $user, 'id');
        $userBranchIds = $userQuery->pluck('id')->all();

        $this->assertCount(2, $userBranchIds);
        $this->assertContains($b1->id, $userBranchIds);
        $this->assertContains($b2->id, $userBranchIds);
        $this->assertNotContains($b3->id, $userBranchIds);

        // Admin: all branches
        $adminQuery = $this->guard->scopeQuery(Branch::query(), $admin, 'id');
        $adminBranchIds = $adminQuery->pluck('id')->all();

        $this->assertCount(3, $adminBranchIds);
    }
}
