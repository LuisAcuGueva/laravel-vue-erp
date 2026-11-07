<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::PendingApproval => 'Pendiente de aprobación',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
            self::PartiallyReceived => 'Parcialmente recibida',
            self::Received => 'Recibida',
            self::Closed => 'Cerrada',
            self::Cancelled => 'Anulada',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::PendingApproval, self::Approved, self::Cancelled], true),
            self::PendingApproval => in_array($to, [self::Approved, self::Rejected, self::Cancelled], true),
            // Editar una OC rechazada la devuelve a borrador.
            self::Rejected => $to === self::Draft,
            self::Approved => in_array($to, [self::PartiallyReceived, self::Received, self::Closed, self::Cancelled], true),
            self::PartiallyReceived => in_array($to, [self::Received, self::Approved, self::Closed], true),
            self::Received => in_array($to, [self::PartiallyReceived, self::Approved], true),
            self::Closed, self::Cancelled => false,
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    /**
     * Estados cuyas líneas siguen reservando cantidad de los requerimientos vinculados.
     */
    public function reservesRequestQuantity(): bool
    {
        return ! in_array($this, [self::Rejected, self::Cancelled], true);
    }

    /**
     * Estados desde los que ya se puede generar el PDF oficial.
     */
    public function isIssued(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyReceived, self::Received, self::Closed], true);
    }
}
