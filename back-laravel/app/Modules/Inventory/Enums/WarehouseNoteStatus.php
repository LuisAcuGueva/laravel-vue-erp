<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum WarehouseNoteStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Approved => 'Aprobado',
            self::Cancelled => 'Anulado',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Approved, self::Cancelled], true),
            self::Approved => $to === self::Cancelled,
            self::Cancelled => false,
        };
    }
}
