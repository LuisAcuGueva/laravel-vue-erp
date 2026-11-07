<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Maintenance;

use App\Modules\Maintenance\Models\Area;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Users\Models\User;
use App\Support\Identity\DocumentType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCsvExports;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class EmployeeExportApiTest extends TestCase
{
    use InteractsWithCsvExports, RefreshDatabase, SeedsUbigeoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        $this->seedUbigeoFixture();
    }

    public function test_export_requires_permission(): void
    {
        $this->authenticateWithPermissions(['employees.view']);

        $this->get('/api/v1/employees/export')->assertForbidden();
    }

    public function test_export_returns_csv_with_relations(): void
    {
        $this->authenticateWithPermissions(['employees.export']);

        $branch = Branch::factory()->create(['name' => 'Sede Central']);
        $area = Area::factory()->create(['name' => 'Ventas']);
        $linked = User::factory()->create(['email' => 'juan@empresa.com']);

        Employee::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'document_type' => DocumentType::Dni,
            'document_number' => '11112222',
            'position' => 'Vendedor',
            'branch_id' => $branch->id,
            'area_id' => $area->id,
            'user_id' => $linked->id,
            'phone' => '999888777',
            'email' => 'jperez@empresa.com',
            'is_active' => true,
        ]);

        $rows = $this->assertCsvDownload($this->get('/api/v1/employees/export'), 'empleados');

        $this->assertSame(
            ['Nombres', 'Documento', 'Cargo', 'Área', 'Sede', 'Teléfono', 'Correo', 'Usuario vinculado', 'Estado'],
            $rows[0],
        );
        $this->assertSame(
            ['Juan Pérez', 'DNI 11112222', 'Vendedor', 'Ventas', 'Sede Central', '999888777', 'jperez@empresa.com', 'juan@empresa.com', 'Activo'],
            $rows[1],
        );
    }

    public function test_export_applies_filters_and_ignores_pagination(): void
    {
        $user = $this->authenticateWithPermissions(['employees.export']);

        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        $user->branches()->attach([$branch->id, $other->id]);

        Employee::factory()->count(3)->create(['branch_id' => $branch->id, 'is_active' => true]);
        Employee::factory()->create(['branch_id' => $branch->id, 'is_active' => false]);
        Employee::factory()->create(['branch_id' => $other->id, 'is_active' => true]);

        $byBranch = $this->assertCsvDownload(
            $this->get("/api/v1/employees/export?branch_id={$branch->id}&is_active=1&per_page=1"),
            'empleados',
        );

        $this->assertCount(4, $byBranch);
    }

    public function test_export_rejects_branch_not_accessible(): void
    {
        $user = $this->authenticateWithPermissions(['employees.export']);

        $assigned = Branch::factory()->create();
        $foreign = Branch::factory()->create();
        $user->branches()->attach($assigned->id);

        Employee::factory()->create(['branch_id' => $foreign->id]);

        $this->getJson("/api/v1/employees/export?branch_id={$foreign->id}")->assertNotFound();
        $this->assertCsvDownload($this->get("/api/v1/employees/export?branch_id={$assigned->id}"), 'empleados');
    }
}
