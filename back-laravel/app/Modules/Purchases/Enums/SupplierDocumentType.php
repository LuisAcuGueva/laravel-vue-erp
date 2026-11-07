<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum SupplierDocumentType: string
{
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case FeeReceipt = 'fee_receipt';
    case SalesNote = 'sales_note';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Factura',
            self::Receipt => 'Boleta de venta',
            self::FeeReceipt => 'Recibo por honorarios',
            self::SalesNote => 'Nota de venta',
            self::Other => 'Otro',
        };
    }
}
