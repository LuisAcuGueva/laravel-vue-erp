<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum PriceType: string
{
    case Retail = 'retail';
    case Wholesale = 'wholesale';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'Minorista',
            self::Wholesale => 'Mayorista',
        };
    }
}
