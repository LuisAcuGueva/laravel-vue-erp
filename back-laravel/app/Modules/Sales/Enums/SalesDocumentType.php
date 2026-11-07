<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

enum SalesDocumentType: string
{
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case SaleNote = 'sale_note';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Factura',
            self::Receipt => 'Boleta de Venta',
            self::SaleNote => 'Nota de Venta',
            self::CreditNote => 'Nota de Crédito',
        };
    }

    public function seriesRegex(): string
    {
        return match ($this) {
            self::Invoice => '/^F[A-Z0-9]{3}$/i',
            self::Receipt => '/^B[A-Z0-9]{3}$/i',
            self::CreditNote => '/^[FB][A-Z0-9]{3}$/i',
            self::SaleNote => '/^[A-Z0-9]{4}$/i',
        };
    }
}
