<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum InventoryCountStatus: string
{
    case Counting = 'counting';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Counting => 'En conteo',
            self::Closed => 'Cerrado',
            self::Cancelled => 'Anulado',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Counting => in_array($to, [self::Closed, self::Cancelled], true),
            self::Closed => $to === self::Cancelled,
            self::Cancelled => false,
        };
    }
}
