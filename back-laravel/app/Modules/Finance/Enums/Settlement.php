<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum Settlement: string
{
    case CashBox = 'cash_box';
    case BankAccount = 'bank_account';
}
