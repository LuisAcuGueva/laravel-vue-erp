<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum CreditNoteStatus: string
{
    case ISSUED = 'issued';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Emitida',
            self::CANCELLED => 'Anulada',
        };
    }
}
