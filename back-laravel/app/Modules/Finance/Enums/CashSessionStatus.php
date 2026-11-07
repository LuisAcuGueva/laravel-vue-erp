<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum CashSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::Closed => 'Cerrada',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Open => $to === self::Closed,
            self::Closed => false,
        };
    }
}
