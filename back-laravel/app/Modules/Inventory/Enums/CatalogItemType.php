<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Enums;

enum CatalogItemType: string
{
    case Product = 'product';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Producto',
            self::Service => 'Servicio',
        };
    }
}
