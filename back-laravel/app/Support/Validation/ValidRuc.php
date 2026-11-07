<?php

declare(strict_types=1);

namespace App\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * RUC peruano (SUNAT): 11 dígitos, prefijo de tipo de contribuyente válido y dígito verificador módulo 11.
 */
class ValidRuc implements ValidationRule
{
    private const PREFIXES = ['10', '15', '16', '17', '20'];

    private const WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('El RUC no es válido: debe tener 11 dígitos y un dígito verificador correcto.');
        }
    }

    public static function isValid(string $ruc): bool
    {
        if (preg_match('/^\d{11}$/', $ruc) !== 1 || ! in_array(substr($ruc, 0, 2), self::PREFIXES, true)) {
            return false;
        }

        $sum = 0;
        foreach (self::WEIGHTS as $i => $weight) {
            $sum += (int) $ruc[$i] * $weight;
        }

        $check = 11 - ($sum % 11);

        return $check % 10 === (int) $ruc[10];
    }
}
