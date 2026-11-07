<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum PaymentCondition: string
{
    case Cash = 'cash';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Contado',
            self::Credit => 'Crédito',
        };
    }
}
