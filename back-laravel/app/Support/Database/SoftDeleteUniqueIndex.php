<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea un índice único que solo aplica a las filas vivas (deleted_at IS NULL).
 *
 * Sin esto, un registro borrado lógicamente impediría volver a crear otro con el
 * mismo email, subdominio u otra columna única. Postgres y SQLite resuelven el
 * conflicto con un índice único parcial; en el resto de motores se crea el índice
 * único normal (prioriza la integridad sobre la reutilización).
 */
final class SoftDeleteUniqueIndex
{
    /** @param array<int, string> $columns */
    public static function make(string $table, array $columns, ?string $name = null): void
    {
        $name ??= $table.'_'.implode('_', $columns).'_unique';

        $connection = Schema::getConnection();

        if (in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            $columnList = implode(', ', array_map(
                static fn (string $column): string => "\"{$column}\"",
                $columns,
            ));

            $connection->statement(
                "CREATE UNIQUE INDEX \"{$name}\" ON \"{$table}\" ({$columnList}) WHERE \"deleted_at\" IS NULL",
            );

            return;
        }

        $connection->getSchemaBuilder()->table(
            $table,
            static fn (Blueprint $blueprint) => $blueprint->unique($columns, $name),
        );
    }
}
