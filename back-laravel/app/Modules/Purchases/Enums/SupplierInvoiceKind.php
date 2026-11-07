<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum SupplierInvoiceKind: string
{
    case Purchase = 'purchase';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Compra',
            self::Expense => 'Gasto',
        };
    }
}
