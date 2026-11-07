<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum WarehouseNoteType: string
{
    case In = 'in';
    case Out = 'out';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Nota de ingreso',
            self::Out => 'Nota de salida',
            self::Transfer => 'Traslado entre almacenes',
        };
    }

    public function codePrefix(): string
    {
        return match ($this) {
            self::In => 'NI',
            self::Out => 'NS',
            self::Transfer => 'NT',
        };
    }
}
