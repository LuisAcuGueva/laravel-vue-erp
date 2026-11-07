<?php

declare(strict_types=1);

namespace App\Support\Identity;

enum DocumentType: string
{
    case Ruc = 'RUC';
    case Dni = 'DNI';
    case Ce = 'CE';
    case Passport = 'PASSPORT';
    case Other = 'OTHER';

    /**
     * @return list<string>
     */
    public function rulesFor(): array
    {
        return match ($this) {
            self::Ruc => ['digits:11', 'regex:/^(10|15|17|20)\d{9}$/'],
            self::Dni => ['digits:8'],
            self::Ce, self::Passport, self::Other => ['alpha_num', 'max:15'],
        };
    }

    /**
     * Document types applicable to natural persons (employees).
     *
     * @return list<self>
     */
    public static function forPersons(): array
    {
        return [
            self::Dni,
            self::Ce,
            self::Passport,
        ];
    }
}
