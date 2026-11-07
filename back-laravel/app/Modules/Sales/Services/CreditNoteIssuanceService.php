<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\SystemReason;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Sales\Enums\CreditNoteReason;
use App\Modules\Sales\Enums\CreditNoteStatus;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Jobs\SendElectronicDocumentJob;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\CreditNoteItem;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Users\Models\User;
use App\Support\Pricing\Decimal;
use App\Support\Pricing\DocumentTotalsCalculator;
use App\Support\Pricing\LineInput;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditNoteIssuanceService
{
    public function __construct(
        private readonly DocumentSeriesService $documentSeriesService,
        private readonly CreditNoteSettlementService $settlementService,
        private readonly StockLedgerService $stockLedger,
    ) {}

    /**
     * Emite una nueva nota de crédito en una transacción atómica.
     *
     * @param  array<string, mixed>  $data
     */
    public function issue(array $data, User $user): CreditNote
    {
        return DB::transaction(function () use ($data, $user): CreditNote {
            /** @var Sale $sale */
            $sale = Sale::with(['items.catalogItem', 'items.itemPresentation', 'series', 'client'])
                ->whereKey($data['sale_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($sale->status !== SaleStatus::Issued) {
                throw ValidationException::withMessages([
                    'sale_id' => 'Solo se pueden emitir notas de crédito sobre ventas emitidas.',
                ]);
            }

            /** @var DocumentSeries $series */
            $series = DocumentSeries::whereKey($data['document_series_id'])->firstOrFail();

            if (! $series->is_active) {
                throw ValidationException::withMessages([
                    'document_series_id' => 'La serie seleccionada no está activa.',
                ]);
            }

            if ($series->document_type !== SalesDocumentType::CreditNote) {
                throw ValidationException::withMessages([
                    'document_series_id' => 'La serie seleccionada no es de tipo nota de crédito.',
                ]);
            }

            if ($series->branch_id !== $sale->branch_id) {
                throw ValidationException::withMessages([
                    'document_series_id' => 'La serie no pertenece a la sede de la venta.',
                ]);
            }

            // Coherencia de serie según el comprobante de la venta
            $saleDocType = $sale->document_type;
            $seriesPrefix = strtoupper(substr($series->series, 0, 1));

            if ($saleDocType === SalesDocumentType::Invoice && $seriesPrefix !== 'F') {
                throw ValidationException::withMessages([
                    'document_series_id' => 'Para una factura electrónica, la serie de la nota de crédito debe iniciar con F (ej. FC01).',
                ]);
            }

            if ($saleDocType === SalesDocumentType::Receipt && $seriesPrefix !== 'B') {
                throw ValidationException::withMessages([
                    'document_series_id' => 'Para una boleta electrónica, la serie de la nota de crédito debe iniciar con B (ej. BC01).',
                ]);
            }

            if ($saleDocType === SalesDocumentType::SaleNote && in_array($seriesPrefix, ['F', 'B'], true)) {
                throw ValidationException::withMessages([
                    'document_series_id' => 'Para una nota de venta, la serie de la nota de crédito no puede iniciar con F o B.',
                ]);
            }

            // Notas de crédito vigentes previas
            /** @var Collection<int, CreditNote> $activeCreditNotes */
            $activeCreditNotes = $sale->creditNotes()
                ->where('status', CreditNoteStatus::ISSUED)
                ->with('items')
                ->get();

            $alreadyCreditedTotal = '0.00';
            $returnedBySaleItemId = [];

            foreach ($activeCreditNotes as $acn) {
                $alreadyCreditedTotal = Decimal::add($alreadyCreditedTotal, (string) $acn->total, 2);
                foreach ($acn->items as $acnItem) {
                    if ($acnItem->sale_item_id) {
                        $returnedBySaleItemId[$acnItem->sale_item_id] = Decimal::add(
                            $returnedBySaleItemId[$acnItem->sale_item_id] ?? '0.00',
                            (string) $acnItem->quantity,
                            4
                        );
                    }
                }
            }

            $maxCreditable = Decimal::sub((string) $sale->total, $alreadyCreditedTotal, 2);
            if (Decimal::cmp($maxCreditable, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    'sale_id' => 'La venta ya ha sido acreditada en su totalidad por notas de crédito anteriores.',
                ]);
            }

            $reason = CreditNoteReason::from((string) $data['reason_code']);

            if ($reason === CreditNoteReason::ANULACION_OPERACION && $activeCreditNotes->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'reason_code' => 'No se puede emitir una anulación de la operación si la venta ya tiene notas de crédito vigentes emitidas.',
                ]);
            }

            $returnWarehouseId = $data['warehouse_id'] ?? $sale->warehouse_id;
            if ($returnWarehouseId) {
                $warehouse = Warehouse::whereKey($returnWarehouseId)->first();
                if ($warehouse && $warehouse->branch_id !== $sale->branch_id) {
                    throw ValidationException::withMessages([
                        'warehouse_id' => 'El almacén de devolución debe pertenecer a la misma sede de la venta.',
                    ]);
                }
            }

            // Construir líneas de la nota de crédito e insumos para el cálculo de totales
            /** @var list<array<string, mixed>> $linesData */
            $linesData = [];
            /** @var list<LineInput> $lineInputs */
            $lineInputs = [];
            /** @var list<StockMovementData> $stockMovements */
            $stockMovements = [];

            $taxRate = (string) $sale->tax_rate;
            $pricesIncludeTax = (bool) $sale->prices_include_tax;

            if ($reason === CreditNoteReason::ANULACION_OPERACION || $reason === CreditNoteReason::DEVOLUCION_TOTAL) {
                $lineNum = 1;
                foreach ($sale->items as $saleItem) {
                    $alreadyRet = $returnedBySaleItemId[$saleItem->id] ?? '0.00';
                    $pendingQty = Decimal::sub((string) $saleItem->quantity, $alreadyRet, 4);

                    if (Decimal::cmp($pendingQty, '0.00', 4) <= 0) {
                        continue;
                    }

                    $canReturn = (bool) ($saleItem->catalogItem->track_stock ?? false);
                    $returnToStock = $canReturn;

                    $taxType = $saleItem->tax_type instanceof TaxType
                        ? $saleItem->tax_type
                        : TaxType::tryFrom((string) $saleItem->tax_type) ?? TaxType::Taxable;

                    $lineInputs[] = new LineInput(
                        quantity: $pendingQty,
                        unitPrice: (string) $saleItem->unit_price,
                        discountPercent: (string) $saleItem->discount_percent,
                        taxType: $taxType
                    );

                    $presentation = $saleItem->itemPresentation;
                    $factor = $presentation !== null ? $presentation->factor : '1';
                    $baseQty = Decimal::mul($pendingQty, (string) $factor, 4);

                    $linesData[] = [
                        'line' => $lineNum++,
                        'sale_item_id' => $saleItem->id,
                        'catalog_item_id' => $saleItem->catalog_item_id,
                        'item_presentation_id' => $saleItem->item_presentation_id,
                        'description' => $saleItem->description,
                        'quantity' => $pendingQty,
                        'base_quantity' => $baseQty,
                        'unit_price' => (string) $saleItem->unit_price,
                        'discount_percent' => (string) $saleItem->discount_percent,
                        'tax_type' => $taxType->value,
                        'return_to_stock' => $returnToStock,
                        'unit_cost' => (string) $saleItem->unit_cost,
                    ];
                }
            } elseif ($reason === CreditNoteReason::DEVOLUCION_ITEM) {
                $requestedItems = (array) ($data['items'] ?? []);
                if (empty($requestedItems)) {
                    throw ValidationException::withMessages([
                        'items' => 'Debe especificar al menos un ítem para devolución parcial.',
                    ]);
                }

                $saleItemsById = $sale->items->keyBy('id');
                $lineNum = 1;

                foreach ($requestedItems as $reqItem) {
                    $saleItemId = (string) $reqItem['sale_item_id'];
                    /** @var SaleItem|null $saleItem */
                    $saleItem = $saleItemsById->get($saleItemId);

                    if ($saleItem === null) {
                        throw ValidationException::withMessages([
                            'items' => 'Uno de los ítems especificados no pertenece a la venta original.',
                        ]);
                    }

                    $alreadyRet = $returnedBySaleItemId[$saleItem->id] ?? '0.00';
                    $pendingQty = Decimal::sub((string) $saleItem->quantity, $alreadyRet, 4);
                    $qty = (string) ($reqItem['quantity'] ?? '0');

                    if (Decimal::cmp($qty, '0.00', 4) <= 0) {
                        throw ValidationException::withMessages([
                            'items' => "La cantidad a devolver debe ser mayor a 0 para el ítem {$saleItem->description}.",
                        ]);
                    }

                    if (Decimal::cmp($qty, $pendingQty, 4) > 0) {
                        throw ValidationException::withMessages([
                            'items' => "La cantidad ({$qty}) excede la cantidad pendiente ({$pendingQty}) para el ítem {$saleItem->description}.",
                        ]);
                    }

                    $canReturn = (bool) ($saleItem->catalogItem->track_stock ?? false);
                    $wantReturn = ! empty($reqItem['return_to_stock']);
                    $returnToStock = $canReturn && $wantReturn;

                    $taxType = $saleItem->tax_type instanceof TaxType
                        ? $saleItem->tax_type
                        : TaxType::tryFrom((string) $saleItem->tax_type) ?? TaxType::Taxable;

                    $lineInputs[] = new LineInput(
                        quantity: $qty,
                        unitPrice: (string) $saleItem->unit_price,
                        discountPercent: (string) $saleItem->discount_percent,
                        taxType: $taxType
                    );

                    $presentation = $saleItem->itemPresentation;
                    $factor = $presentation !== null ? $presentation->factor : '1';
                    $baseQty = Decimal::mul($qty, (string) $factor, 4);

                    $linesData[] = [
                        'line' => $lineNum++,
                        'sale_item_id' => $saleItem->id,
                        'catalog_item_id' => $saleItem->catalog_item_id,
                        'item_presentation_id' => $saleItem->item_presentation_id,
                        'description' => $saleItem->description,
                        'quantity' => $qty,
                        'base_quantity' => $baseQty,
                        'unit_price' => (string) $saleItem->unit_price,
                        'discount_percent' => (string) $saleItem->discount_percent,
                        'tax_type' => $taxType->value,
                        'return_to_stock' => $returnToStock,
                        'unit_cost' => (string) $saleItem->unit_cost,
                    ];
                }
            } elseif ($reason === CreditNoteReason::DESCUENTO_ITEM || $reason === CreditNoteReason::DISMINUCION_VALOR) {
                $requestedItems = (array) ($data['items'] ?? []);
                if (empty($requestedItems)) {
                    throw ValidationException::withMessages([
                        'items' => 'Debe especificar al menos un ítem para aplicar el ajuste.',
                    ]);
                }

                $saleItemsById = $sale->items->keyBy('id');
                $lineNum = 1;

                foreach ($requestedItems as $reqItem) {
                    $saleItemId = (string) $reqItem['sale_item_id'];
                    /** @var SaleItem|null $saleItem */
                    $saleItem = $saleItemsById->get($saleItemId);

                    if ($saleItem === null) {
                        throw ValidationException::withMessages([
                            'items' => 'Uno de los ítems especificados no pertenece a la venta original.',
                        ]);
                    }

                    $amount = (string) ($reqItem['amount'] ?? '0');
                    if (Decimal::cmp($amount, '0.00', 2) <= 0) {
                        throw ValidationException::withMessages([
                            'items' => 'El monto del ajuste debe ser mayor a 0.',
                        ]);
                    }

                    $taxType = $saleItem->tax_type instanceof TaxType
                        ? $saleItem->tax_type
                        : TaxType::tryFrom((string) $saleItem->tax_type) ?? TaxType::Taxable;

                    $descSuffix = $reason === CreditNoteReason::DESCUENTO_ITEM ? 'Descuento en' : 'Disminución de valor en';

                    $lineInputs[] = new LineInput(
                        quantity: '1.0000',
                        unitPrice: $amount,
                        discountPercent: '0.00',
                        taxType: $taxType
                    );

                    $linesData[] = [
                        'line' => $lineNum++,
                        'sale_item_id' => $saleItem->id,
                        'catalog_item_id' => $saleItem->catalog_item_id,
                        'item_presentation_id' => null,
                        'description' => "{$descSuffix} {$saleItem->description}",
                        'quantity' => '1.0000',
                        'base_quantity' => '1.0000',
                        'unit_price' => $amount,
                        'discount_percent' => '0.00',
                        'tax_type' => $taxType->value,
                        'return_to_stock' => false,
                        'unit_cost' => '0.000000',
                    ];
                }
            } elseif ($reason === CreditNoteReason::DESCUENTO_GLOBAL) {
                $amount = (string) ($data['amount'] ?? '0');
                if (Decimal::cmp($amount, '0.00', 2) <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'El monto del descuento global debe ser mayor a 0.',
                    ]);
                }

                $taxType = TaxType::Taxable;

                $lineInputs[] = new LineInput(
                    quantity: '1.0000',
                    unitPrice: $amount,
                    discountPercent: '0.00',
                    taxType: $taxType
                );

                $linesData[] = [
                    'line' => 1,
                    'sale_item_id' => null,
                    'catalog_item_id' => null,
                    'item_presentation_id' => null,
                    'description' => 'Descuento global de la venta',
                    'quantity' => '1.0000',
                    'base_quantity' => '1.0000',
                    'unit_price' => $amount,
                    'discount_percent' => '0.00',
                    'tax_type' => $taxType->value,
                    'return_to_stock' => false,
                    'unit_cost' => '0.000000',
                ];
            }

            if (empty($lineInputs)) {
                throw ValidationException::withMessages([
                    'items' => 'No hay ítems válidos para generar la nota de crédito.',
                ]);
            }

            // Calcular totales
            $calculator = new DocumentTotalsCalculator;
            $totals = $calculator->calculate($lineInputs, $taxRate, $pricesIncludeTax);

            if (Decimal::cmp((string) $totals->total, $maxCreditable, 2) > 0) {
                throw ValidationException::withMessages([
                    'total' => "El importe total de la nota ({$totals->total}) excede el monto máximo acreditable ({$maxCreditable}).",
                ]);
            }

            // Obtener número correlativo
            $next = $this->documentSeriesService->next($series);

            $issueDate = $data['issue_date'] ?? now()->toDateString();
            $settlement = (array) ($data['settlement'] ?? []);

            /** @var CreditNote $creditNote */
            $creditNote = CreditNote::create([
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'document_series_id' => $series->id,
                'series' => $series->series,
                'number' => $next['number'],
                'full_number' => $next['full'],
                'issue_date' => $issueDate,
                'issued_at' => now(),
                'reason_code' => $reason,
                'reason_description' => trim((string) $data['reason_description']),
                'client_id' => $sale->client_id,
                'client_document_type' => $sale->client_document_type,
                'client_document_number' => $sale->client_document_number,
                'client_name' => $sale->client_name,
                'client_address' => $sale->client_address,
                'currency_id' => $sale->currency_id,
                'exchange_rate' => $sale->exchange_rate,
                'tax_rate' => $sale->tax_rate,
                'prices_include_tax' => $sale->prices_include_tax,
                'taxable_base' => $totals->taxableBase,
                'exempt_base' => $totals->exemptBase,
                'unaffected_base' => $totals->unaffectedBase,
                'tax_total' => $totals->taxTotal,
                'total' => $totals->total,
                'applied_to_balance' => '0.00',
                'refunded_amount' => '0.00',
                'settlement_mode' => $settlement['mode'] ?? 'auto',
                'refund_payment_method_id' => null,
                'refund_cash_session_id' => null,
                'warehouse_id' => $returnWarehouseId,
                'status' => CreditNoteStatus::ISSUED,
                'einvoice_status' => $series->is_electronic ? 'pending' : 'not_applicable',
                'created_by' => $user->id,
            ]);

            // Guardar líneas
            foreach ($linesData as $idx => $lineData) {
                $calcLine = $totals->lines[$idx];

                CreditNoteItem::create(array_merge($lineData, [
                    'credit_note_id' => $creditNote->id,
                    'line_subtotal' => $calcLine->subtotal,
                    'line_tax' => $calcLine->tax,
                    'line_total' => $calcLine->total,
                ]));

                // Preparar movimiento de inventario si devuelve al stock
                if ($lineData['return_to_stock'] && $returnWarehouseId && $lineData['catalog_item_id']) {
                    $stockMovements[] = new StockMovementData(
                        warehouseId: (string) $returnWarehouseId,
                        catalogItemId: (string) $lineData['catalog_item_id'],
                        baseQuantity: (float) $lineData['base_quantity'],
                        type: MovementType::SaleReturn,
                        source: $creditNote,
                        sourceNumber: $creditNote->full_number,
                        unitCost: (float) $lineData['unit_cost'],
                        presentationId: $lineData['item_presentation_id'],
                        presentationQuantity: (float) $lineData['quantity'],
                        reasonCode: SystemReason::SaleReturn->value,
                        notes: "Devolución por Nota de Crédito {$creditNote->full_number}",
                        movedAt: Carbon::parse($issueDate)->startOfDay(),
                    );
                }
            }

            // Aplicar movimientos de stock en kardex
            if (! empty($stockMovements)) {
                $this->stockLedger->applyBatch($stockMovements);
            }

            // Aplicar liquidación financiera (saldo o reembolso)
            $this->settlementService->apply($creditNote, $settlement, $user);

            // Encolar comprobante para envío electrónico después del commit
            if ($series->is_electronic) {
                DB::afterCommit(function () use ($creditNote) {
                    dispatch(new SendElectronicDocumentJob($creditNote->id, 'credit_note'));
                });
            }

            return $creditNote->refresh();
        });
    }

    /**
     * Devuelve información precargada de la venta para inicializar el formulario de nota de crédito.
     *
     * @return array<string, mixed>
     */
    public function prefill(string $saleId): array
    {
        /** @var Sale $sale */
        $sale = Sale::with(['items.catalogItem.baseUnit', 'items.itemPresentation', 'series', 'client', 'currency'])
            ->whereKey($saleId)
            ->firstOrFail();

        /** @var Collection<int, CreditNote> $activeNotes */
        $activeNotes = $sale->creditNotes()
            ->where('status', CreditNoteStatus::ISSUED)
            ->with('items')
            ->get();

        $alreadyCreditedTotal = '0.00';
        $returnedBySaleItemId = [];

        foreach ($activeNotes as $acn) {
            $alreadyCreditedTotal = Decimal::add($alreadyCreditedTotal, (string) $acn->total, 2);
            foreach ($acn->items as $acnItem) {
                if ($acnItem->sale_item_id) {
                    $returnedBySaleItemId[$acnItem->sale_item_id] = Decimal::add(
                        $returnedBySaleItemId[$acnItem->sale_item_id] ?? '0.00',
                        (string) $acnItem->quantity,
                        4
                    );
                }
            }
        }

        $maxCreditable = Decimal::sub((string) $sale->total, $alreadyCreditedTotal, 2);
        if (Decimal::cmp($maxCreditable, '0.00', 2) < 0) {
            $maxCreditable = '0.00';
        }

        $lines = $sale->items->map(function (SaleItem $item) use ($returnedBySaleItemId): array {
            $alreadyRet = $returnedBySaleItemId[$item->id] ?? '0.00';
            $pendingQty = Decimal::sub((string) $item->quantity, $alreadyRet, 4);
            if (Decimal::cmp($pendingQty, '0.00', 4) < 0) {
                $pendingQty = '0.0000';
            }

            return [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'already_returned' => $alreadyRet,
                'pending_quantity' => $pendingQty,
                'unit_price' => (string) $item->unit_price,
                'discount_percent' => (string) $item->discount_percent,
                'tax_type' => is_object($item->tax_type) ? $item->tax_type->value : (string) $item->tax_type,
                'unit_cost' => (string) $item->unit_cost,
                'track_stock' => (bool) ($item->catalogItem ? $item->catalogItem->track_stock : false),
                'unit_symbol' => $item->catalogItem?->baseUnit ? $item->catalogItem->baseUnit->symbol : 'NIU',
            ];
        })->values()->all();

        // Series de nota de crédito compatibles con la venta
        $saleDocType = $sale->document_type;
        $seriesQuery = DocumentSeries::query()
            ->where('branch_id', $sale->branch_id)
            ->where('document_type', SalesDocumentType::CreditNote)
            ->where('is_active', true);

        if ($saleDocType === SalesDocumentType::Invoice) {
            $seriesQuery->where('series', 'like', 'F%');
        } elseif ($saleDocType === SalesDocumentType::Receipt) {
            $seriesQuery->where('series', 'like', 'B%');
        } else {
            $seriesQuery->where('series', 'not like', 'F%')
                ->where('series', 'not like', 'B%');
        }

        $series = $seriesQuery->orderBy('series')->get()->map(fn (DocumentSeries $s): array => [
            'id' => $s->id,
            'series' => $s->series,
            'is_electronic' => $s->is_electronic,
        ])->all();

        return [
            'sale' => [
                'id' => $sale->id,
                'full_number' => $sale->full_number,
                'document_type' => $saleDocType->value,
                'issue_date' => $sale->issue_date,
                'total' => (string) $sale->total,
                'balance' => (string) $sale->balance,
                'currency' => [
                    'id' => $sale->currency->id,
                    'code' => $sale->currency->code,
                    'symbol' => $sale->currency->symbol,
                    'decimals' => $sale->currency->decimals,
                ],
                'client' => [
                    'id' => $sale->client->id,
                    'name' => $sale->client_name,
                    'document_type' => $sale->client_document_type,
                    'document_number' => $sale->client_document_number,
                ],
                'warehouse_id' => $sale->warehouse_id,
            ],
            'lines' => $lines,
            'max_creditable' => $maxCreditable,
            'balance' => (string) $sale->balance,
            'series' => $series,
        ];
    }
}
