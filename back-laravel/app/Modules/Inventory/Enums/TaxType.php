<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum TaxType: string
{
    case Taxable = 'taxable';
    case Exempt = 'exempt';
    case Unaffected = 'unaffected';

    public function label(): string
    {
        return match ($this) {
            self::Taxable => 'Gravado',
            self::Exempt => 'Exonerado',
            self::Unaffected => 'Inafecto',
        };
    }
}
