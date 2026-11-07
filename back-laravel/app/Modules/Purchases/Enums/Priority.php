<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum Priority: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Urgent => 'Urgente',
        };
    }
}
