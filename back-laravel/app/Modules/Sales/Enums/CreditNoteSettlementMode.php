<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum CreditNoteSettlementMode: string
{
    case AUTO = 'auto';
    case REFUND = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::AUTO => 'Aplicar a deuda y reembolsar remanente',
            self::REFUND => 'Reembolso directo',
        };
    }
}
