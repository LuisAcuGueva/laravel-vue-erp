<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum PurchaseReceiptStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Confirmed => 'Confirmada',
            self::Cancelled => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Confirmed => 'success',
            self::Cancelled => 'error',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Confirmed, self::Cancelled], true),
            self::Confirmed => $to === self::Cancelled,
            self::Cancelled => false,
        };
    }
}
