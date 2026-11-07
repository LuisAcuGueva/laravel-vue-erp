<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum InstallmentStatus: string
{
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::PartiallyPaid => 'Pago parcial',
            self::Paid => 'Pagada',
            self::Cancelled => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
            self::Cancelled => 'error',
        };
    }
}
