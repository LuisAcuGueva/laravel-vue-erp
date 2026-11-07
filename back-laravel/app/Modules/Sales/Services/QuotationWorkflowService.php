<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Enums\QuotationStatus;
use App\Modules\Sales\Exceptions\SalesRuleException;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

class QuotationWorkflowService
{
    /**
     * @throws SalesRuleException
     */
    public function send(Quotation $quotation, User $user): Quotation
    {
        if (! $quotation->status->canTransitionTo(QuotationStatus::Sent)) {
            throw new SalesRuleException("No se puede enviar una cotización en estado {$quotation->status->label()}.");
        }

        $quotation->update([
            'status' => QuotationStatus::Sent,
            'sent_at' => now(),
        ]);

        return $quotation->fresh(['client', 'contact', 'seller', 'currency']) ?? $quotation;
    }

    /**
     * @throws SalesRuleException
     */
    public function accept(Quotation $quotation, User $user): Quotation
    {
        if (! $quotation->status->canTransitionTo(QuotationStatus::Accepted)) {
            throw new SalesRuleException("No se puede aceptar una cotización en estado {$quotation->status->label()}.");
        }

        $today = now()->startOfDay();
        if ($quotation->valid_until->copy()->startOfDay()->lt($today)) {
            throw new SalesRuleException('No se puede aceptar una cotización vencida.');
        }

        $quotation->update([
            'status' => QuotationStatus::Accepted,
            'accepted_at' => now(),
        ]);

        return $quotation->fresh(['client', 'contact', 'seller', 'currency']) ?? $quotation;
    }

    /**
     * @throws SalesRuleException
     */
    public function reject(Quotation $quotation, User $user, ?string $reason = null): Quotation
    {
        if (! $quotation->status->canTransitionTo(QuotationStatus::Rejected)) {
            throw new SalesRuleException("No se puede rechazar una cotización en estado {$quotation->status->label()}.");
        }

        $quotation->update([
            'status' => QuotationStatus::Rejected,
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $quotation->fresh(['client', 'contact', 'seller', 'currency']) ?? $quotation;
    }

    /**
     * @throws SalesRuleException
     */
    public function cancel(Quotation $quotation, User $user, ?string $reason = null): Quotation
    {
        if (! $quotation->status->canTransitionTo(QuotationStatus::Cancelled)) {
            throw new SalesRuleException("No se puede anular una cotización en estado {$quotation->status->label()}.");
        }

        $quotation->update([
            'status' => QuotationStatus::Cancelled,
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $quotation->fresh(['client', 'contact', 'seller', 'currency']) ?? $quotation;
    }

    /**
     * @throws SalesRuleException
     */
    public function markConverted(Quotation $quotation, Model $sale): Quotation
    {
        if (! $quotation->status->canTransitionTo(QuotationStatus::Converted)) {
            throw new SalesRuleException("No se puede convertir a venta una cotización en estado {$quotation->status->label()}.");
        }

        $quotation->update([
            'status' => QuotationStatus::Converted,
            'converted_sale_id' => (string) $sale->getAttribute('id'),
        ]);

        return $quotation->fresh(['client', 'contact', 'seller', 'currency']) ?? $quotation;
    }

    /**
     * @throws SalesRuleException
     */
    public function revertConverted(Quotation $quotation): Quotation
    {
        if (! $quotation->status->canTransitionTo(QuotationStatus::Accepted)) {
            throw new SalesRuleException("No se puede revertir una cotización en estado {$quotation->status->label()}.");
        }

        $quotation->update([
            'status' => QuotationStatus::Accepted,
            'converted_sale_id' => null,
        ]);

        return $quotation->fresh(['client', 'contact', 'seller', 'currency']) ?? $quotation;
    }

    /**
     * Pasa masivamente a 'expired' las cotizaciones 'sent' cuya fecha de vigencia haya pasado.
     */
    public function expireOverdue(): int
    {
        return Quotation::where('status', QuotationStatus::Sent->value)
            ->where('valid_until', '<', now()->toDateString())
            ->update(['status' => QuotationStatus::Expired->value]);
    }
}
