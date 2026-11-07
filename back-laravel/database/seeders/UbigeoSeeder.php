<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Ubigeo\Models\Ubigeo;
use App\Modules\Ubigeo\Services\UbigeoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Carga el catálogo INEI desde `database/data/ubigeo_inei.csv`. Idempotente (upsert por `code`):
 * nunca borra filas; los códigos ausentes del CSV quedan `is_active = false`.
 */
class UbigeoSeeder extends Seeder
{
    public string $path;

    public function __construct()
    {
        $this->path = database_path('data/ubigeo_inei.csv');
    }

    public function run(): void
    {
        $rows = $this->readRows();
        $now = Carbon::now();

        /** @var array<string, string> $existing */
        $existing = Ubigeo::query()->pluck('id', 'code')->all();

        $records = array_map(fn (array $row): array => [
            'id' => $existing[$row['code']] ?? (string) Str::uuid(),
            'code' => $row['code'],
            'department_code' => substr($row['code'], 0, 2),
            'province_code' => substr($row['code'], 0, 4),
            'department' => $row['department'],
            'province' => $row['province'],
            'district' => $row['district'],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        foreach (array_chunk($records, 500) as $chunk) {
            Ubigeo::query()->upsert(
                $chunk,
                uniqueBy: ['code'],
                update: ['department_code', 'province_code', 'department', 'province', 'district', 'is_active', 'updated_at'],
            );
        }

        Ubigeo::query()
            ->whereNotIn('code', array_column($rows, 'code'))
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => $now]);

        app(UbigeoService::class)->flushCache();
    }

    /**
     * @return list<array{code: string, department: string, province: string, district: string}>
     */
    private function readRows(): array
    {
        $handle = @fopen($this->path, 'r');

        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el catálogo de ubigeo: {$this->path}");
        }

        try {
            $header = fgetcsv($handle, escape: '');

            if ($header !== ['code', 'department', 'province', 'district']) {
                throw new RuntimeException('Cabecera inválida en el catálogo de ubigeo (línea 1).');
            }

            $rows = [];
            $line = 1;

            while (($data = fgetcsv($handle, escape: '')) !== false) {
                $line++;

                if ($data === [null]) {
                    continue;
                }

                [$code, $department, $province, $district] = array_map(
                    fn (?string $value): string => trim((string) $value),
                    array_pad($data, 4, null),
                );

                if (preg_match('/^\d{6}$/', $code) !== 1 || $department === '' || $province === '' || $district === '') {
                    throw new RuntimeException("Fila inválida en el catálogo de ubigeo (línea {$line}).");
                }

                $rows[] = compact('code', 'department', 'province', 'district');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
