<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Clients;

use App\Modules\Clients\Models\Client;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCsvExports;
use Tests\TestCase;

class ClientExportApiTest extends TestCase
{
    use InteractsWithCsvExports, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
    }

    public function test_export_requires_permission(): void
    {
        $this->authenticateWithPermissions(['clients.view']);

        $this->get('/api/v1/clients/export')->assertForbidden();
    }

    public function test_export_returns_csv_with_headers_and_rows(): void
    {
        $this->authenticateWithPermissions(['clients.export']);

        Client::factory()->create(['name' => 'Comercial Andina', 'description' => '=cmd|calc']);

        $rows = $this->assertCsvDownload($this->get('/api/v1/clients/export'), 'clientes');

        $this->assertSame(['Nombre', 'Descripción', 'Fecha de registro'], $rows[0]);
        $this->assertSame('Comercial Andina', $rows[1][0]);
        $this->assertSame("'=cmd|calc", $rows[1][1]);
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', (string) $rows[1][2]);
    }

    public function test_export_applies_search_and_ignores_pagination(): void
    {
        $this->authenticateWithPermissions(['clients.export']);

        Client::factory()->count(3)->create(['name' => 'Distribuidora Norte']);
        Client::factory()->create(['name' => 'Ferretería Sur']);

        $rows = $this->assertCsvDownload($this->get('/api/v1/clients/export?search=Norte&per_page=1'), 'clientes');

        $this->assertCount(4, $rows);
        $this->assertSame(['Distribuidora Norte'], array_values(array_unique(array_column(array_slice($rows, 1), 0))));
    }
}
