<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum SaleStatus: string
{
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Emitida',
            self::Cancelled => 'Anulada',
        };
    }
}
