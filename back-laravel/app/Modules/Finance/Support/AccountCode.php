<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\FinancialAccount;

final class AccountCode
{
    public const PATTERN = '/^\d{2,8}$/';

    public static function isValid(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1;
    }

    /**
     * Una cuenta raíz tiene exactamente 2 dígitos; una hija empieza con el código de su padre y es más larga.
     */
    public static function isValidChildOf(string $code, ?string $parentCode): bool
    {
        if (! self::isValid($code)) {
            return false;
        }

        if ($parentCode === null || $parentCode === '') {
            return strlen($code) === 2;
        }

        return str_starts_with($code, $parentCode) && strlen($code) > strlen($parentCode);
    }

    public static function levelFor(?FinancialAccount $parent): int
    {
        return $parent === null ? 1 : $parent->level + 1;
    }

    /**
     * Padre derivado: el código más largo de la lista que sea prefijo propio de $code.
     *
     * @param  list<string>  $codes
     */
    public static function parentOf(string $code, array $codes): ?string
    {
        $parent = null;

        foreach ($codes as $candidate) {
            if (strlen($candidate) < strlen($code) && str_starts_with($code, $candidate)
                && ($parent === null || strlen($candidate) > strlen($parent))) {
                $parent = $candidate;
            }
        }

        return $parent;
    }
}
