<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum EInvoiceStatus: string
{
    case NotApplicable = 'not_applicable';
    case Pending = 'pending';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::NotApplicable => 'No aplica',
            self::Pending => 'Pendiente',
            self::Sent => 'Enviado',
            self::Accepted => 'Aceptado',
            self::Rejected => 'Rechazado',
            self::Error => 'Error',
        };
    }
}
