<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case PartiallyOrdered = 'partially_ordered';
    case Ordered = 'ordered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Submitted => 'Enviado',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
            self::PartiallyOrdered => 'Parcialmente ordenado',
            self::Ordered => 'Ordenado',
            self::Cancelled => 'Anulado',
        };
    }

    /**
     * Aprobado, parcialmente ordenado y ordenado se alternan según las cantidades incluidas en órdenes de compra.
     */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Submitted, self::Cancelled], true),
            self::Submitted => in_array($to, [self::Approved, self::Rejected, self::Cancelled], true),
            self::Approved => in_array($to, [self::PartiallyOrdered, self::Ordered, self::Cancelled], true),
            self::PartiallyOrdered => in_array($to, [self::Approved, self::Ordered], true),
            self::Ordered => in_array($to, [self::Approved, self::PartiallyOrdered], true),
            self::Rejected, self::Cancelled => false,
        };
    }

    public function isOpenForOrders(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyOrdered], true);
    }
}
