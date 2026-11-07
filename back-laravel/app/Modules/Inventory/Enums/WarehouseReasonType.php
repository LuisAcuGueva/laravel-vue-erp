<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum WarehouseReasonType: string
{
    case In = 'in';
    case Out = 'out';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Ingreso',
            self::Out => 'Salida',
            self::Transfer => 'Traslado',
            self::Adjustment => 'Ajuste',
        };
    }
}
