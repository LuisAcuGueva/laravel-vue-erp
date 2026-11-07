<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Users;

use App\Modules\Maintenance\Models\Branch;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithCsvExports;
use Tests\TestCase;

class UserExportApiTest extends TestCase
{
    use InteractsWithCsvExports, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
    }

    public function test_export_requires_permission(): void
    {
        $this->authenticateWithPermissions(['users.view']);

        $this->get('/api/v1/users/export')->assertForbidden();
    }

    public function test_export_lists_roles_and_branches_separated_by_semicolon(): void
    {
        $this->authenticateWithPermissions(['users.export']);

        $user = User::factory()->create([
            'name' => 'Ana Torres',
            'email' => 'ana@empresa.com',
            'status' => User::STATUS_ACTIVE,
        ]);
        $user->assignRole(Role::where('name', 'manager')->firstOrFail(), Role::where('name', 'user')->firstOrFail());
        $user->branches()->attach([
            Branch::factory()->create(['name' => 'Sede Norte'])->id,
            Branch::factory()->create(['name' => 'Sede Central'])->id,
        ]);

        $rows = $this->assertCsvDownload($this->get('/api/v1/users/export?search=Ana'), 'usuarios');

        $this->assertSame(['Nombre', 'Correo', 'Roles', 'Sedes', 'Estado', 'Último acceso'], $rows[0]);
        $this->assertSame(
            ['Ana Torres', 'ana@empresa.com', 'manager; user', 'Sede Central; Sede Norte', 'Activo', ''],
            $rows[1],
        );
    }

    public function test_export_applies_status_and_role_filters_and_ignores_pagination(): void
    {
        $this->authenticateWithPermissions(['users.export']);

        $manager = Role::where('name', 'manager')->firstOrFail();

        User::factory()->count(3)->create(['status' => User::STATUS_INACTIVE])
            ->each(fn (User $u) => $u->assignRole($manager));
        User::factory()->create(['status' => User::STATUS_ACTIVE])->assignRole($manager);

        $rows = $this->assertCsvDownload(
            $this->get('/api/v1/users/export?status=inactive&role=manager&per_page=1'),
            'usuarios',
        );

        $this->assertCount(4, $rows);
        $this->assertSame(['Inactivo'], array_values(array_unique(array_column(array_slice($rows, 1), 4))));
    }

    public function test_export_eager_loads_relations_without_n_plus_one(): void
    {
        $this->authenticateWithPermissions(['users.export']);

        $branch = Branch::factory()->create();
        User::factory()->count(10)->create()->each(fn (User $u) => $u->branches()->attach($branch->id));

        DB::enableQueryLog();
        $this->get('/api/v1/users/export')->streamedContent();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(15, $queries);
    }
}
