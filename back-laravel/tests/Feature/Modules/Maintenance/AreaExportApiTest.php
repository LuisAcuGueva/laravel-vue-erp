<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Maintenance;

use App\Modules\Maintenance\Models\Area;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCsvExports;
use Tests\TestCase;

class AreaExportApiTest extends TestCase
{
    use InteractsWithCsvExports, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
    }

    public function test_export_requires_permission(): void
    {
        $this->authenticateWithPermissions(['areas.view']);

        $this->get('/api/v1/areas/export')->assertForbidden();
    }

    public function test_export_returns_csv_with_headers_and_rows(): void
    {
        $this->authenticateWithPermissions(['areas.export']);

        Area::factory()->create(['name' => 'Ventas', 'description' => 'Área comercial', 'is_active' => true]);

        $rows = $this->assertCsvDownload($this->get('/api/v1/areas/export'), 'areas');

        $this->assertSame(['Nombre', 'Descripción', 'Estado'], $rows[0]);
        $this->assertSame(['Ventas', 'Área comercial', 'Activo'], $rows[1]);
    }

    public function test_export_applies_search_and_status_filters(): void
    {
        $this->authenticateWithPermissions(['areas.export']);

        Area::factory()->create(['name' => 'Ventas', 'is_active' => true]);
        Area::factory()->create(['name' => 'Ventas Corporativas', 'is_active' => false]);
        Area::factory()->create(['name' => 'Logística', 'is_active' => true]);

        $search = $this->assertCsvDownload($this->get('/api/v1/areas/export?search=Ventas'), 'areas');
        $this->assertSame(['Ventas', 'Ventas Corporativas'], array_column(array_slice($search, 1), 0));

        $active = $this->assertCsvDownload($this->get('/api/v1/areas/export?search=Ventas&is_active=1'), 'areas');
        $this->assertSame(['Ventas'], array_column(array_slice($active, 1), 0));
    }

    public function test_export_includes_all_rows_ignoring_pagination(): void
    {
        $this->authenticateWithPermissions(['areas.export']);

        Area::factory()->count(4)->create();

        $rows = $this->assertCsvDownload($this->get('/api/v1/areas/export?per_page=1'), 'areas');

        $this->assertCount(5, $rows);
    }
}
