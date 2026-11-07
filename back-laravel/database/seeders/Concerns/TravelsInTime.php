<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Permite que un seeder "viaje en el tiempo" para que now()/created_at/opened_at,
 * etc. reflejen una fecha y hora de negocio realista, en vez de siempre "ahora".
 * Los servicios de dominio (CashSessionService, SaleIssuanceService, etc.) usan
 * now() internamente para varios timestamps; esta es la única forma de obtener
 * fechas realistas en los seeders demo sin escribir esos campos directamente.
 */
trait TravelsInTime
{
    /**
     * Ejecuta $callback como si el reloj del sistema estuviera en $when, y
     * siempre restaura la hora real al terminar (incluso si $callback lanza).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function at(CarbonInterface|string $when, callable $callback): mixed
    {
        $moment = $when instanceof CarbonInterface ? $when : Carbon::parse($when);

        Carbon::setTestNow($moment);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
