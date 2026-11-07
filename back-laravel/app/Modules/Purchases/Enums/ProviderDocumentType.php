<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum ProviderDocumentType: string
{
    case Guide = 'guide';
    case Invoice = 'invoice';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Guide => 'Guía de remisión',
            self::Invoice => 'Factura',
            self::Other => 'Otro',
        };
    }
}
