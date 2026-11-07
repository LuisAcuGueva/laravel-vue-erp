<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum CashTransferStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Approved, self::Rejected, self::Cancelled], true),
            self::Approved, self::Rejected, self::Cancelled => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'error',
            self::Cancelled => 'secondary',
        };
    }
}
