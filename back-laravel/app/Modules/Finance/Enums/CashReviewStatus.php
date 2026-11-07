<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum CashReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de revisión',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Pending => in_array($to, [self::Approved, self::Rejected], true),
            self::Approved, self::Rejected => false,
        };
    }
}
