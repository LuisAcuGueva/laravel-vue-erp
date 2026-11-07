<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum BankOperationType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Depósito',
            self::Withdrawal => 'Retiro',
            self::Transfer => 'Transferencia',
        };
    }
}
