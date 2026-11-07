<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum MoneyDirection: string
{
    case In = 'in';
    case Out = 'out';
}
