<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Modules\Maintenance\Models\Branch;
use App\Support\Export\CsvExport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function exporter(): CsvExport
    {
        return new class extends CsvExport
        {
            public int $chunks = 0;

            protected function columns(): array
            {
                return [
                    'code' => 'Código',
                    'name' => 'Nombre',
                    'is_main' => 'Principal',
                    'created_at' => 'Creado',
                    'phone' => 'Teléfono',
                ];
            }

            protected function prepareChunk(Collection $rows): void
            {
                $this->chunks++;
            }

            protected function map(Model $row): array
            {
                /** @var Branch $row */
                return [
                    'code' => $row->code,
                    'name' => $row->name,
                    'is_main' => $row->is_main,
                    'created_at' => $row->created_at,
                    'phone' => $row->phone,
                ];
            }
        };
    }

    private function render(CsvExport $exporter): string
    {
        $handle = fopen('php://memory', 'w+b');
        $exporter->write($handle, Branch::query()->orderBy('code'));
        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    public function test_writes_bom_headers_and_formatted_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 20:30:00', 'UTC'));
        Branch::factory()->create(['code' => 'SEDE-01', 'name' => 'Sede Central', 'is_main' => true, 'phone' => null]);
        Branch::factory()->create(['code' => 'SEDE-02', 'name' => 'Sede, Norte', 'is_main' => false, 'phone' => '01-1234567']);

        $content = $this->render($this->exporter());

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $lines = explode("\n", trim(substr($content, 3)));

        $this->assertSame('Código,Nombre,Principal,Creado,Teléfono', $lines[0]);
        $this->assertSame('SEDE-01,"Sede Central",Sí,"16/10/2026 15:30",', $lines[1]);
        $this->assertSame('SEDE-02,"Sede, Norte",No,"16/10/2026 15:30",01-1234567', $lines[2]);
    }

    public function test_neutralizes_csv_injection(): void
    {
        Branch::factory()->create(['code' => 'SEDE-01', 'name' => '=HYPERLINK("http://x")', 'phone' => '+51999']);
        Branch::factory()->create(['code' => 'SEDE-02', 'name' => '@SUM(A1)', 'phone' => '-1']);

        $rows = array_map(
            fn (string $line) => str_getcsv($line, ',', '"', ''),
            explode("\n", trim(substr($this->render($this->exporter()), 3)))
        );

        $this->assertSame("'=HYPERLINK(\"http://x\")", $rows[1][1]);
        $this->assertSame("'+51999", $rows[1][4]);
        $this->assertSame("'@SUM(A1)", $rows[2][1]);
        $this->assertSame("'-1", $rows[2][4]);
    }

    public function test_processes_rows_in_chunks(): void
    {
        Branch::factory()->count(3)->create();

        $exporter = $this->exporter();
        $content = $this->render($exporter);

        $this->assertCount(4, explode("\n", trim($content)));
        $this->assertSame(1, $exporter->chunks);
    }

    public function test_download_returns_stream_with_lima_timestamp_filename(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 20:30:00', 'UTC'));
        Branch::factory()->create(['code' => 'SEDE-01']);

        $response = $this->exporter()->download(Branch::query(), 'sedes');

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="sedes_20261016_1530.csv"', $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $this->assertStringContainsString('SEDE-01', $body);
    }
}
