<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

use App\Support\Pricing\Decimal;

/**
 * Estado de una factura de proveedor y de cada una de sus cuotas.
 */
enum SupplierInvoiceStatus: string
{
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::PartiallyPaid => 'Pago parcial',
            self::Paid => 'Pagada',
            self::Cancelled => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
            self::Cancelled => 'error',
        };
    }

    /**
     * Estado que corresponde a un documento según lo pagado frente a su total.
     */
    public static function fromAmounts(string $total, string $paid): self
    {
        if (Decimal::cmp($paid, '0', 2) <= 0) {
            return self::Pending;
        }

        return Decimal::cmp($paid, $total, 2) >= 0 ? self::Paid : self::PartiallyPaid;
    }
}
