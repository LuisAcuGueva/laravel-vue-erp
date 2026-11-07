<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ubigeo;

use App\Modules\Ubigeo\Models\Ubigeo;
use Database\Seeders\UbigeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UbigeoCatalogCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_catalog_has_all_departments_provinces_and_districts(): void
    {
        (new UbigeoSeeder)->run();

        $lines = file(database_path('data/ubigeo_inei.csv'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertIsArray($lines);

        $this->assertSame(count($lines) - 1, Ubigeo::query()->count());
        $this->assertSame(25, Ubigeo::query()->distinct()->count('department_code'));
        $this->assertSame(196, Ubigeo::query()->distinct()->count('province_code'));

        $departments = Ubigeo::query()->distinct()->pluck('department_code')->all();
        Ubigeo::query()->distinct()->pluck('province_code')->each(
            fn (string $province) => $this->assertContains(substr($province, 0, 2), $departments),
        );

        $this->assertDatabaseHas('ubigeos', ['code' => '150122', 'department' => 'LIMA', 'province' => 'LIMA', 'district' => 'MIRAFLORES']);
        $this->assertDatabaseHas('ubigeos', ['code' => '070101', 'department' => 'CALLAO', 'province' => 'CALLAO', 'district' => 'CALLAO']);
    }
}
