<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum FinancialAccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Activo',
            self::Liability => 'Pasivo',
            self::Equity => 'Patrimonio',
            self::Income => 'Ingreso',
            self::Expense => 'Gasto',
        };
    }
}
