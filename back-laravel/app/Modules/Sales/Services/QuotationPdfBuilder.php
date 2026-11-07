<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Enums\QuotationStatus;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\QuotationItem;

/**
 * Prepara los datos para la vista PDF de una cotización.
 */
class QuotationPdfBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Quotation $quotation): array
    {
        $quotation->loadMissing([
            'client',
            'contact',
            'seller',
            'currency',
            'items.catalogItem',
            'items.presentation.unit',
        ]);

        return [
            'watermark' => $this->watermark($quotation->status),
            'quotation' => [
                'number' => $quotation->number,
                'issue_date' => $quotation->issue_date->format('d/m/Y'),
                'valid_until' => $quotation->valid_until->format('d/m/Y'),
                'seller' => $quotation->seller ? $quotation->seller->full_name : '—',
                'payment_terms' => $quotation->payment_terms ?? 'Contado',
                'delivery_time' => $quotation->delivery_time ?? '—',
                'currency_code' => $quotation->currency->code,
                'currency_symbol' => $quotation->currency->symbol,
                'exchange_rate' => $quotation->exchange_rate,
                'prices_include_tax' => $quotation->prices_include_tax,
                'tax_rate' => $quotation->tax_rate,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'client' => [
                    'name' => $quotation->client->name,
                    'document_type' => $quotation->client->document_type ? $quotation->client->document_type->value : 'DOC',
                    'document_number' => $quotation->client->document_number ?? '—',
                    'address' => $quotation->client->address,
                    'phone' => $quotation->client->phone,
                    'email' => $quotation->client->email,
                    'contact' => $quotation->contact?->name,
                ],
            ],
            'items' => $quotation->items->map(fn (QuotationItem $item): array => [
                'code' => $item->catalogItem?->code,
                'description' => $item->description,
                'unit' => $item->presentation && $item->presentation->unit ? $item->presentation->unit->symbol : 'UND',
                'quantity' => $item->quantity,
                'list_price' => $item->list_price,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'total' => $item->line_total,
            ])->all(),
            'totals' => [
                'taxable_base' => $quotation->taxable_base,
                'exempt_base' => $quotation->exempt_base,
                'unaffected_base' => $quotation->unaffected_base,
                'discount_total' => $quotation->discount_total,
                'tax_total' => $quotation->tax_total,
                'total' => $quotation->total,
            ],
        ];
    }

    private function watermark(QuotationStatus $status): ?string
    {
        return match ($status) {
            QuotationStatus::Draft => 'BORRADOR',
            QuotationStatus::Rejected => 'RECHAZADA',
            QuotationStatus::Expired => 'VENCIDA',
            QuotationStatus::Cancelled => 'ANULADA',
            default => null,
        };
    }
}
