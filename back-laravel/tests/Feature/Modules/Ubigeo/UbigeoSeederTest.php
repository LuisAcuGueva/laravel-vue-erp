<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Ubigeo;

use App\Modules\Ubigeo\Models\Ubigeo;
use Database\Seeders\UbigeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\SeedsUbigeoFixture;
use Tests\TestCase;

class UbigeoSeederTest extends TestCase
{
    use RefreshDatabase, SeedsUbigeoFixture;

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpPath = sys_get_temp_dir().'/ubigeo_test_'.uniqid().'.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpPath);
        parent::tearDown();
    }

    private function seedFrom(string $csv): void
    {
        file_put_contents($this->tmpPath, $csv);

        $seeder = new UbigeoSeeder;
        $seeder->path = $this->tmpPath;
        $seeder->run();
    }

    public function test_seeder_loads_rows_and_derives_codes(): void
    {
        $this->seedUbigeoFixture();

        $this->assertDatabaseCount('ubigeos', 10);
        $this->assertDatabaseHas('ubigeos', [
            'code' => '150102',
            'department_code' => '15',
            'province_code' => '1501',
            'department' => 'LIMA',
            'province' => 'LIMA',
            'district' => 'ANCÓN',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('ubigeos', ['code' => '090710', 'district' => 'ÑAHUIMPUQUIO']);
    }

    public function test_seeding_twice_does_not_duplicate_and_keeps_ids(): void
    {
        $this->seedUbigeoFixture();
        $idBefore = Ubigeo::query()->where('code', '150122')->value('id');

        $this->seedUbigeoFixture();

        $this->assertDatabaseCount('ubigeos', 10);
        $this->assertSame($idBefore, Ubigeo::query()->where('code', '150122')->value('id'));
    }

    public function test_changed_name_is_updated_and_missing_code_is_deactivated(): void
    {
        $this->seedFrom("code,department,province,district\n150101,LIMA,LIMA,LIMA\n150122,LIMA,LIMA,MIRAFLORES\n");
        $idBefore = Ubigeo::query()->where('code', '150122')->value('id');

        $this->seedFrom("code,department,province,district\n150122,LIMA,LIMA,MIRAFLORES NUEVO\n");

        $this->assertDatabaseCount('ubigeos', 2);
        $this->assertDatabaseHas('ubigeos', ['id' => $idBefore, 'code' => '150122', 'district' => 'MIRAFLORES NUEVO', 'is_active' => true]);
        $this->assertDatabaseHas('ubigeos', ['code' => '150101', 'is_active' => false]);
    }

    public function test_invalid_row_throws_exception_with_line_number(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('línea 3');

        $this->seedFrom("code,department,province,district\n150101,LIMA,LIMA,LIMA\n15A122,LIMA,LIMA,MIRAFLORES\n");
    }
}
