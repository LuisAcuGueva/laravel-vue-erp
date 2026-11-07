<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Sent => 'Enviada',
            self::Accepted => 'Aceptada',
            self::Rejected => 'Rechazada',
            self::Expired => 'Vencida',
            self::Converted => 'Convertida a venta',
            self::Cancelled => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Rejected => 'warning',
            self::Expired => 'error',
            self::Converted => 'primary',
            self::Cancelled => 'error',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return false;
        }

        return match ($this) {
            self::Draft => in_array($next, [self::Sent, self::Cancelled], true),
            self::Sent => in_array($next, [self::Accepted, self::Converted, self::Rejected, self::Expired, self::Cancelled], true),
            self::Accepted => in_array($next, [self::Converted, self::Cancelled], true),
            self::Converted => $next === self::Accepted,
            self::Rejected, self::Expired, self::Cancelled => false,
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Rejected, self::Expired, self::Converted, self::Cancelled], true);
    }
}
