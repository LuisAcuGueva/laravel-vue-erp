<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Clients\Resources\ClientResource;
use App\Modules\Maintenance\Resources\EmployeeResource;
use App\Modules\Sales\Enums\QuotationStatus;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\QuotationItem;
use App\Modules\Sales\Models\Sale;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function findById(string $id): ?Sale
    {
        return Sale::with([
            'items.catalogItem.baseUnit',
            'items.itemPresentation',
            'payments.paymentMethod',
            'payments.cashSession.box',
            'series',
            'client',
            'seller',
            'warehouse',
            'currency',
            'creator',
            'canceller',
            'discountOverrideUser',
            'kardexMovements',
            'installments.allocations.collection',
            'collectionAllocations.collection',
        ])->find($id);
    }

    /**
     * Extrae datos precargados de una cotización para inicializar el carrito del POS o la venta.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function prefillFromQuotation(string $quotationId, string $branchId): array
    {
        /** @var Quotation|null $quotation */
        $quotation = Quotation::with([
            'client',
            'seller',
            'currency',
            'items.catalogItem.baseUnit',
            'items.presentation',
        ])->find($quotationId);

        if ($quotation === null) {
            throw ValidationException::withMessages([
                'quotation_id' => 'La cotización indicada no existe.',
            ]);
        }

        if ($quotation->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'quotation_id' => 'La cotización no pertenece a la sede activa.',
            ]);
        }

        if (! in_array($quotation->status, [QuotationStatus::Sent, QuotationStatus::Accepted], true)) {
            throw ValidationException::withMessages([
                'quotation_id' => "La cotización no está en estado vigente o aceptada (estado: {$quotation->status->label()}).",
            ]);
        }

        return [
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->number,
            'client_id' => $quotation->client_id,
            'client' => new ClientResource($quotation->client),
            'seller_id' => $quotation->seller_id,
            'seller' => $quotation->seller ? new EmployeeResource($quotation->seller) : null,
            'currency_id' => $quotation->currency_id,
            'price_type' => is_object($quotation->price_type) ? $quotation->price_type->value : $quotation->price_type,
            'prices_include_tax' => $quotation->prices_include_tax,
            'global_discount_percent' => $quotation->global_discount_percent,
            'notes' => $quotation->notes,
            'items' => $quotation->items->map(fn (QuotationItem $item): array => [
                'line' => $item->line,
                'catalog_item_id' => $item->catalog_item_id,
                'item_presentation_id' => $item->item_presentation_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'list_price' => $item->list_price,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'tax_type' => is_object($item->tax_type) ? $item->tax_type->value : $item->tax_type,
                'item' => $item->catalogItem ? [
                    'id' => $item->catalogItem->id,
                    'name' => $item->catalogItem->name,
                    'code' => $item->catalogItem->code,
                    'unit_symbol' => $item->catalogItem->baseUnit?->symbol,
                ] : null,
                'presentation' => $item->presentation ? [
                    'id' => $item->presentation->id,
                    'name' => $item->presentation->name,
                    'factor' => $item->presentation->factor,
                ] : null,
            ])->values()->all(),
        ];
    }
}
