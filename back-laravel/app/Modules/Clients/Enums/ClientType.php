<?php

declare(strict_types=1);

namespace App\Modules\Clients\Enums;

enum ClientType: string
{
    case Person = 'person';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Persona Natural',
            self::Company => 'Persona Jurídica',
        };
    }
}
