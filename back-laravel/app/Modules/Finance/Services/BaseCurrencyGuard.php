<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\ExchangeRate;

class BaseCurrencyGuard
{
    /**
     * Comprueba si existen operaciones financieras o tipos de cambio registrados
     * que impidan modificar la moneda base del sistema.
     */
    public static function hasOperations(): bool
    {
        return ExchangeRate::query()->exists();
    }

    /**
     * @throws FinanceRuleException
     */
    public static function assertCanChangeBase(): void
    {
        if (self::hasOperations()) {
            throw new FinanceRuleException('No se puede cambiar la moneda base con operaciones registradas', 'is_base');
        }
    }
}
