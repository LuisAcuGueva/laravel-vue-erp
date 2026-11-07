<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum PaymentMethodKind: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Wallet = 'wallet';
    case Check = 'check';
    case Other = 'other';
}
