<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum BankAccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Other = 'other';
}
