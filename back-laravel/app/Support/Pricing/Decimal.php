<?php

declare(strict_types=1);

namespace App\Support\Pricing;

/**
 * Envoltura tipada de bcmath: acepta cadenas decimales y trata como '0' lo que no sea numérico.
 */
final class Decimal
{
    public static function add(string $a, string $b, int $scale = 6): string
    {
        return bcadd(self::numeric($a), self::numeric($b), $scale);
    }

    public static function sub(string $a, string $b, int $scale = 6): string
    {
        return bcsub(self::numeric($a), self::numeric($b), $scale);
    }

    public static function mul(string $a, string $b, int $scale = 6): string
    {
        return bcmul(self::numeric($a), self::numeric($b), $scale);
    }

    public static function div(string $a, string $b, int $scale = 6): string
    {
        return bcdiv(self::numeric($a), self::numeric($b), $scale);
    }

    public static function cmp(string $a, string $b, int $scale = 6): int
    {
        return bccomp(self::numeric($a), self::numeric($b), $scale);
    }

    /**
     * @return numeric-string
     */
    private static function numeric(string $value): string
    {
        return is_numeric($value) ? $value : '0';
    }
}
