<?php

declare(strict_types=1);

namespace App\Support\Export;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportador CSV genérico de listados (spec 031, design §2.2).
 *
 * Escribe UTF-8 con BOM (Excel lo abre como UTF-8), encabezados en español y
 * recorre la consulta por lotes con `lazy()` para mantener la memoria acotada.
 */
abstract class CsvExport
{
    public const CHUNK_SIZE = 500;

    public const TIMEZONE = 'America/Lima';

    private const BOM = "\xEF\xBB\xBF";

    /** Prefijos que una hoja de cálculo interpretaría como fórmula (CSV injection). */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @return array<string, string> clave => encabezado en español
     */
    abstract protected function columns(): array;

    /**
     * @return array<string, scalar|CarbonInterface|null> fila con las mismas claves que columns()
     */
    abstract protected function map(Model $row): array;

    /**
     * Hook opcional para precargar datos por lote (ej. ubigeos o relaciones).
     *
     * @param  Collection<int, Model>  $rows
     */
    protected function prepareChunk(Collection $rows): void {}

    /**
     * @param  Builder<covariant Model>  $query
     */
    public function download(Builder $query, string $baseName): StreamedResponse
    {
        return new StreamedResponse(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            $this->write($handle, $query);
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($baseName).'"',
            'Cache-Control' => 'no-store, no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Escribe el CSV completo en el recurso indicado.
     *
     * @param  resource  $handle
     * @param  Builder<covariant Model>  $query
     */
    public function write($handle, Builder $query): void
    {
        $columns = $this->columns();

        fwrite($handle, self::BOM);
        fputcsv($handle, array_values($columns), ',', '"', '');

        $query->lazy(self::CHUNK_SIZE)
            ->chunk(self::CHUNK_SIZE)
            ->each(function (LazyCollection $chunk) use ($handle, $columns): void {
                $rows = $chunk->collect();
                $this->prepareChunk($rows);

                foreach ($rows as $row) {
                    $mapped = $this->map($row);
                    $line = [];

                    foreach (array_keys($columns) as $key) {
                        $line[] = $this->formatValue($mapped[$key] ?? null);
                    }

                    fputcsv($handle, $line, ',', '"', '');
                }
            });
    }

    public function filename(string $baseName): string
    {
        return $baseName.'_'.Carbon::now(self::TIMEZONE)->format('Ymd_Hi').'.csv';
    }

    protected function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Sí' : 'No',
            is_int($value), is_float($value) => (string) $value,
            $value instanceof CarbonInterface => $value->copy()->setTimezone(self::TIMEZONE)->format('d/m/Y H:i'),
            default => $this->neutralize((string) $value),
        };
    }

    /**
     * Antepone un apóstrofo a las celdas que una hoja de cálculo evaluaría como fórmula.
     */
    protected function neutralize(string $value): string
    {
        if ($value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }
}
