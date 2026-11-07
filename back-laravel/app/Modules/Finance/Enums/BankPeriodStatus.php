<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum BankPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::Closed => 'Cerrado',
        };
    }
}
