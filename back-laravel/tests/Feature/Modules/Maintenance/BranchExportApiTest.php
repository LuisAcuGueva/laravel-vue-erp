<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Maintenance;

use App\Modules\Maintenance\Models\Branch;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCsvExports;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class BranchExportApiTest extends TestCase
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
        $this->authenticateWithPermissions(['branches.view']);

        $this->get('/api/v1/branches/export')->assertForbidden();
    }

    public function test_export_returns_csv_with_spanish_headers_and_formatted_rows(): void
    {
        $this->authenticateWithPermissions(['branches.export']);

        Branch::factory()->main()->create([
            'code' => 'SEDE-01',
            'name' => 'Sede Central',
            'address' => 'Av. Principal 123',
            'ubigeo' => '150101',
            'phone' => '01-1234567',
            'email' => 'sede@empresa.com',
            'is_active' => true,
        ]);

        $rows = $this->assertCsvDownload($this->get('/api/v1/branches/export'), 'sedes');

        $this->assertSame(
            ['Código', 'Nombre', 'Dirección', 'Ubicación', 'Teléfono', 'Correo', 'Principal', 'Usuarios', 'Estado'],
            $rows[0],
        );
        $this->assertSame('SEDE-01', $rows[1][0]);
        $this->assertNotSame('', $rows[1][3]);
        $this->assertStringNotContainsString('150101', $rows[1][3]);
        $this->assertSame('Sí', $rows[1][6]);
        $this->assertSame('0', $rows[1][7]);
        $this->assertSame('Activo', $rows[1][8]);
    }

    public function test_export_applies_search_and_status_filters(): void
    {
        $this->authenticateWithPermissions(['branches.export']);

        Branch::factory()->create(['code' => 'BR-001', 'name' => 'Sede Norte', 'is_active' => true]);
        Branch::factory()->create(['code' => 'BR-002', 'name' => 'Sede Sur', 'is_active' => false]);
        Branch::factory()->create(['code' => 'BR-003', 'name' => 'Almacén Norte', 'is_active' => false]);

        $search = $this->assertCsvDownload($this->get('/api/v1/branches/export?search=Norte'), 'sedes');
        $this->assertSame(['BR-001', 'BR-003'], array_column(array_slice($search, 1), 0));

        $inactive = $this->assertCsvDownload($this->get('/api/v1/branches/export?is_active=0&search=Sur'), 'sedes');
        $this->assertSame(['BR-002'], array_column(array_slice($inactive, 1), 0));
    }

    public function test_export_includes_all_rows_ignoring_pagination(): void
    {
        $this->authenticateWithPermissions(['branches.export']);

        Branch::factory()->count(5)->create();

        $rows = $this->assertCsvDownload($this->get('/api/v1/branches/export?per_page=2&page=2'), 'sedes');

        $this->assertCount(6, $rows);
    }
}
