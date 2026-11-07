<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum BankOperationStatus: string
{
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completado',
            self::Cancelled => 'Anulado',
        };
    }
}
