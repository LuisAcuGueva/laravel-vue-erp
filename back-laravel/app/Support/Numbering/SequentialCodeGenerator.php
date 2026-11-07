<?php

declare(strict_types=1);

namespace App\Support\Numbering;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SequentialCodeGenerator
{
    /**
     * Genera el siguiente código correlativo de manera atómica para una clave y scope dados.
     *
     * @param  string  $key  Identificador de la secuencia (ej. 'catalog_item', 'warehouse_note')
     * @param  string  $prefix  Prefijo textual (ej. 'ITM', 'NI')
     * @param  int  $pad  Cantidad de dígitos con relleno de ceros a la izquierda (default 6)
     * @param  string|null  $scope  Ámbito de la secuencia (ej. branch_id o 'global')
     * @return string Código con formato PREFIX-000001
     */
    public static function next(string $key, string $prefix, int $pad = 6, ?string $scope = null): string
    {
        $scope = ($scope === null || trim($scope) === '') ? 'global' : trim($scope);

        return DB::transaction(function () use ($key, $prefix, $pad, $scope): string {
            DB::table('code_sequences')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'scope' => $scope,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('code_sequences')
                ->where('key', $key)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new \RuntimeException("No se pudo bloquear la secuencia para la clave '{$key}'.");
            }

            $nextNumber = ((int) $sequence->last_number) + 1;

            DB::table('code_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            $cleanPrefix = rtrim($prefix, '-');
            $paddedNumber = str_pad((string) $nextNumber, $pad, '0', STR_PAD_LEFT);

            return $cleanPrefix === '' ? $paddedNumber : "{$cleanPrefix}-{$paddedNumber}";
        });
    }

    /**
     * Reserva un número ya usado (ej. por un seeder de demo) para que `next()` nunca lo repita.
     * Solo avanza la secuencia; nunca la retrocede.
     *
     * @param  string  $number  Código ya asignado (ej. 'NI-000001')
     */
    public static function reserve(string $key, string $number, ?string $scope = null): void
    {
        $scope = ($scope === null || trim($scope) === '') ? 'global' : trim($scope);

        if (! preg_match('/(\d+)$/', $number, $matches)) {
            return;
        }

        $usedNumber = (int) $matches[1];

        DB::transaction(function () use ($key, $scope, $usedNumber): void {
            DB::table('code_sequences')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'scope' => $scope,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('code_sequences')
                ->where('key', $key)
                ->where('scope', $scope)
                ->where('last_number', '<', $usedNumber)
                ->update([
                    'last_number' => $usedNumber,
                    'updated_at' => now(),
                ]);
        });
    }
}
