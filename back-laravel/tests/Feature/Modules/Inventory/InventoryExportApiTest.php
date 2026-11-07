<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Inventory\Enums\WarehouseReasonType;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseReason;
use App\Modules\Maintenance\Models\Branch;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCsvExports;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class InventoryExportApiTest extends TestCase
{
    use InteractsWithCsvExports, RefreshDatabase, SeedsUbigeoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
        $this->seedUbigeoFixture();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function endpoints(): array
    {
        return [
            'categorías' => ['categories', 'categories'],
            'marcas' => ['brands', 'brands'],
            'unidades' => ['units', 'units'],
            'almacenes' => ['warehouses', 'warehouses'],
            'motivos' => ['warehouse-reasons', 'warehouse_reasons'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_export_requires_permission(string $path, string $module): void
    {
        $this->authenticateWithPermissions(["{$module}.view"]);

        $this->get("/api/v1/inventory/{$path}/export")->assertForbidden();
    }

    public function test_categories_export_includes_path_and_level(): void
    {
        $this->authenticateWithPermissions(['categories.export']);

        $root = Category::factory()->create(['code' => 'TEC', 'name' => 'Tecnología', 'depth' => 0]);
        Category::factory()->create(['code' => 'TEC-COM', 'name' => 'Cómputo', 'parent_id' => $root->id, 'depth' => 1]);

        $rows = $this->assertCsvDownload($this->get('/api/v1/inventory/categories/export?per_page=1'), 'categorias');

        $this->assertSame(['Código', 'Nombre', 'Ruta', 'Nivel', 'Descripción', 'Estado'], $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertSame(['TEC', 'Tecnología', 'Tecnología', '1'], array_slice($rows[1], 0, 4));
        $this->assertSame(['TEC-COM', 'Cómputo', 'Tecnología › Cómputo', '2'], array_slice($rows[2], 0, 4));
    }

    public function test_brands_export_applies_filters(): void
    {
        $this->authenticateWithPermissions(['brands.export']);

        Brand::factory()->create(['name' => 'Samsung', 'is_active' => true]);
        Brand::factory()->create(['name' => 'Sony', 'is_active' => false]);

        $rows = $this->assertCsvDownload($this->get('/api/v1/inventory/brands/export?is_active=1'), 'marcas');

        $this->assertSame(['Nombre', 'Descripción', 'Estado'], $rows[0]);
        $this->assertSame(['Samsung'], array_column(array_slice($rows, 1), 0));
    }

    public function test_units_export_leaves_packaging_factor_empty(): void
    {
        $this->authenticateWithPermissions(['units.export']);

        $packaging = UnitCategory::factory()->create(['name' => 'Empaque', 'is_packaging' => true]);
        Unit::factory()->create(['unit_category_id' => $packaging->id, 'name' => 'Caja', 'symbol' => 'CJA', 'sunat_code' => 'BX']);

        $rows = $this->assertCsvDownload($this->get('/api/v1/inventory/units/export'), 'unidades');

        $this->assertSame('Factor de conversión', $rows[0][4]);
        $this->assertSame(['Empaque', 'Caja', 'CJA', 'No', '', 'Sí', 'BX', 'Activo'], $rows[1]);
    }

    public function test_warehouse_reasons_export_translates_type(): void
    {
        $this->authenticateWithPermissions(['warehouse_reasons.export']);

        WarehouseReason::factory()->create(['code' => 'SHRINKAGE', 'name' => 'Merma', 'type' => WarehouseReasonType::Out, 'requires_approval' => true, 'is_system' => false]);

        $rows = $this->assertCsvDownload($this->get('/api/v1/inventory/warehouse-reasons/export'), 'motivos_almacen');

        $this->assertSame(['SHRINKAGE', 'Merma', 'Salida', 'Sí', 'No', 'Activo'], $rows[1]);
    }

    public function test_warehouses_export_only_includes_accessible_branches(): void
    {
        $user = $this->authenticateWithPermissions(['warehouses.export']);
        $mine = Branch::factory()->create(['name' => 'Sede Norte']);
        $other = Branch::factory()->create(['name' => 'Sede Sur']);
        $user->branches()->sync([$mine->id => ['is_default' => true]]);

        Warehouse::factory()->create(['branch_id' => $mine->id, 'code' => 'ALM01']);
        Warehouse::factory()->create(['branch_id' => $other->id, 'code' => 'ALM02']);

        $rows = $this->assertCsvDownload($this->get('/api/v1/inventory/warehouses/export'), 'almacenes');

        $this->assertSame(['Código', 'Nombre', 'Sede', 'Dirección', 'Por defecto', 'Estado'], $rows[0]);
        $this->assertSame(['ALM01'], array_column(array_slice($rows, 1), 0));

        $this->get("/api/v1/inventory/warehouses/export?branch_id={$other->id}")->assertNotFound();
    }
}
