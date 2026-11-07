<?php

declare(strict_types=1);

namespace App\Support\Text;

use App\Modules\Finance\Models\Currency;

class NumberToWords
{
    /**
     * @var array<int, string>
     */
    private const UNITS = [
        0 => 'CERO',
        1 => 'UNO',
        2 => 'DOS',
        3 => 'TRES',
        4 => 'CUATRO',
        5 => 'CINCO',
        6 => 'SEIS',
        7 => 'SIETE',
        8 => 'OCHO',
        9 => 'NUEVE',
        10 => 'DIEZ',
        11 => 'ONCE',
        12 => 'DOCE',
        13 => 'TRECE',
        14 => 'CATORCE',
        15 => 'QUINCE',
        16 => 'DIECISÉIS',
        17 => 'DIECISIETE',
        18 => 'DIECIOCHO',
        19 => 'DIECINUEVE',
        20 => 'VEINTE',
        21 => 'VEINTIUNO',
        22 => 'VEINTIDÓS',
        23 => 'VEINTITRÉS',
        24 => 'VEINTICUATRO',
        25 => 'VEINTICINCO',
        26 => 'VEINTISÉIS',
        27 => 'VEINTISIETE',
        28 => 'VEINTIOCHO',
        29 => 'VEINTINUEVE',
    ];

    /**
     * @var array<int, string>
     */
    private const TENS = [
        30 => 'TREINTA',
        40 => 'CUARENTA',
        50 => 'CINCUENTA',
        60 => 'SESENTA',
        70 => 'SETENTA',
        80 => 'OCHENTA',
        90 => 'NOVENTA',
    ];

    /**
     * @var array<int, string>
     */
    private const HUNDREDS = [
        100 => 'CIENTO',
        200 => 'DOSCIENTOS',
        300 => 'TRESCIENTOS',
        400 => 'CUATROCIENTOS',
        500 => 'QUINIENTOS',
        600 => 'SEISCIENTOS',
        700 => 'SETECIENTOS',
        800 => 'OCHOCIENTOS',
        900 => 'NOVECIENTOS',
    ];

    /**
     * Convierte un número entero positivo (hasta 999 999 999 999) a letras en español.
     */
    public static function toWords(int $number, bool $asAdjective = false): string
    {
        if ($number === 0) {
            return self::UNITS[0];
        }

        if ($number < 0) {
            return 'MENOS '.self::toWords(abs($number), $asAdjective);
        }

        if ($number === 100) {
            return 'CIEN';
        }

        return self::convert($number, $asAdjective);
    }

    /**
     * Convierte un monto numérico o decimal al formato de comprobante comercial:
     * "SON: CIENTO VEINTE CON 50/100 SOLES"
     */
    public static function toInvoiceText(
        string|float|int $amount,
        Currency|string $currency = 'PEN',
        bool $prefix = true
    ): string {
        $amountStr = is_string($amount) ? trim($amount) : number_format((float) $amount, 2, '.', '');

        if (! str_contains($amountStr, '.')) {
            $amountStr .= '.00';
        }

        [$integerPartStr, $decimalPartStr] = explode('.', $amountStr, 2);
        $integerPart = (int) $integerPartStr;
        $decimalPart = str_pad(substr($decimalPartStr, 0, 2), 2, '0');

        $words = self::toWords($integerPart, asAdjective: true);

        $currencyName = self::resolveCurrencyName($currency, $integerPart);

        $result = "{$words} CON {$decimalPart}/100 {$currencyName}";

        return $prefix ? "SON: {$result}" : $result;
    }

    private static function convert(int $n, bool $asAdjective = false): string
    {
        if ($n < 30) {
            if ($asAdjective && $n === 1) {
                return 'UN';
            }
            if ($asAdjective && $n === 21) {
                return 'VEINTIÚN';
            }

            return self::UNITS[$n];
        }

        if ($n < 100) {
            $ten = (int) (floor($n / 10) * 10);
            $unit = $n % 10;
            if ($unit === 0) {
                return self::TENS[$ten];
            }
            $unitWord = ($asAdjective && $unit === 1) ? 'UN' : self::UNITS[$unit];

            return self::TENS[$ten].' Y '.$unitWord;
        }

        if ($n < 1000) {
            if ($n === 100) {
                return 'CIEN';
            }
            $hundred = (int) (floor($n / 100) * 100);
            $remainder = $n % 100;
            if ($remainder === 0) {
                return self::HUNDREDS[$hundred];
            }

            return self::HUNDREDS[$hundred].' '.self::convert($remainder, $asAdjective);
        }

        if ($n < 1000000) {
            $thousands = (int) floor($n / 1000);
            $remainder = $n % 1000;

            $thousandsWord = $thousands === 1 ? 'MIL' : self::convert($thousands, asAdjective: true).' MIL';

            if ($remainder === 0) {
                return $thousandsWord;
            }

            return $thousandsWord.' '.self::convert($remainder, $asAdjective);
        }

        if ($n < 1000000000000) {
            $millions = (int) floor($n / 1000000);
            $remainder = $n % 1000000;

            $millionsWord = $millions === 1
                ? 'UN MILLÓN'
                : self::convert($millions, asAdjective: true).' MILLONES';

            if ($remainder === 0) {
                return $millionsWord;
            }

            return $millionsWord.' '.self::convert($remainder, $asAdjective);
        }

        return (string) $n;
    }

    private static function resolveCurrencyName(Currency|string $currency, int $integerPart): string
    {
        $code = $currency instanceof Currency ? strtoupper($currency->code) : strtoupper(trim($currency));

        return match ($code) {
            'PEN', 'SOL', 'SOLES', 'S/' => 'SOLES',
            'USD', 'DÓLAR', 'DÓLARES', '$' => 'DÓLARES AMERICANOS',
            'EUR', 'EURO', 'EUROS', '€' => 'EUROS',
            default => $currency instanceof Currency ? strtoupper($currency->name) : $code,
        };
    }
}
