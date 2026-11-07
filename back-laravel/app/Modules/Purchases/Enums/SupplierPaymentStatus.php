<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum SupplierPaymentStatus: string
{
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Registrado',
            self::Cancelled => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Cancelled => 'error',
        };
    }
}
