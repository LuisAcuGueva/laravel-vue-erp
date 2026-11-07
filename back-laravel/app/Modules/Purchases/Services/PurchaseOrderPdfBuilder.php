<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;

/**
 * Arma los datos de la vista PDF de una orden de compra.
 */
class PurchaseOrderPdfBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(PurchaseOrder $order): array
    {
        $order->loadMissing(['provider', 'warehouse', 'currency', 'items.catalogItem', 'items.presentation.unit']);

        return [
            'watermark' => $this->watermark($order->status),
            'order' => [
                'number' => $order->number,
                'order_date' => $order->order_date->format('d/m/Y'),
                'expected_date' => $order->expected_date?->format('d/m/Y'),
                'warehouse' => $order->warehouse->name,
                'payment_condition' => $order->payment_condition->label(),
                'credit_days' => $order->credit_days,
                'currency_code' => $order->currency->code,
                'currency_symbol' => $order->currency->symbol,
                'exchange_rate' => $order->exchange_rate,
                'prices_include_tax' => $order->prices_include_tax,
                'tax_rate' => $order->tax_rate,
                'notes' => $order->notes,
                'terms' => $order->terms,
                'provider' => [
                    'business_name' => $order->provider->business_name,
                    'document_type' => $order->provider->document_type->value,
                    'document_number' => $order->provider->document_number,
                    'address' => $order->provider->address,
                    'phone' => $order->provider->phone,
                    'email' => $order->provider->email,
                ],
            ],
            'items' => $order->items->map(fn (PurchaseOrderItem $item): array => [
                'code' => $item->catalogItem?->code,
                'description' => $item->catalogItem !== null
                    ? $item->catalogItem->name.($item->presentation !== null ? ' ('.$item->presentation->name.')' : '')
                    : (string) $item->description,
                'unit' => $item->presentation?->unit?->symbol,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'total' => $item->line_total,
            ])->all(),
            'totals' => [
                'taxable_base' => $order->taxable_base,
                'exempt_base' => $order->exempt_base,
                'unaffected_base' => $order->unaffected_base,
                'discount_total' => $order->discount_total,
                'tax_total' => $order->tax_total,
                'total' => $order->total,
            ],
        ];
    }

    private function watermark(PurchaseOrderStatus $status): ?string
    {
        return match ($status) {
            PurchaseOrderStatus::Draft, PurchaseOrderStatus::PendingApproval, PurchaseOrderStatus::Rejected => 'BORRADOR',
            PurchaseOrderStatus::Cancelled => 'ANULADA',
            default => null,
        };
    }
}
