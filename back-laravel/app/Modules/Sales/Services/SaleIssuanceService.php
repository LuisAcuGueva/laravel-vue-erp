<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Clients\Models\Client;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Data\SettlementData;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Services\BoxAccessService;
use App\Modules\Finance\Services\MoneySettlementService;
use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Sales\Contracts\AfterSaleIssuedHook;
use App\Modules\Sales\Enums\EInvoiceStatus;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Enums\QuotationStatus;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Events\SaleIssued;
use App\Modules\Sales\Jobs\SendElectronicDocumentJob;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SalePayment;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Identity\DocumentType;
use App\Support\Pricing\Decimal;
use App\Support\Pricing\DocumentTotalsCalculator;
use App\Support\Pricing\LineInput;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleIssuanceService
{
    /**
     * @param  iterable<AfterSaleIssuedHook>  $afterIssueHooks
     */
    public function __construct(
        private readonly DocumentSeriesService $seriesService,
        private readonly CreditPolicyService $creditPolicy,
        private readonly StockLedgerService $stockLedger,
        private readonly MoneySettlementService $settlement,
        private readonly SalePricingService $pricing,
        private readonly DiscountPolicyService $discountPolicy,
        private readonly QuotationWorkflowService $quotationWorkflow,
        private readonly BoxAccessService $boxAccessService,
        private readonly BranchAccessGuard $branchAccessGuard,
        private readonly SystemParameterService $parameterService,
        private readonly iterable $afterIssueHooks = [],
    ) {}

    /**
     * Emite una venta de forma atómica en una única transacción de base de datos.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function issue(array $data, User $user, ?Branch $branch = null): Sale
    {
        $activeBranch = $branch ?? app(ActiveBranch::class)->get();
        $this->branchAccessGuard->assertAccessible($user, $activeBranch->id);

        // 1. Validar Serie
        if (empty($data['document_series_id'])) {
            throw ValidationException::withMessages(['document_series_id' => 'Debe seleccionar una serie de comprobante.']);
        }
        /** @var DocumentSeries $series */
        $series = DocumentSeries::whereKey($data['document_series_id'])->firstOrFail();
        if ($series->branch_id !== $activeBranch->id) {
            throw ValidationException::withMessages(['document_series_id' => 'La serie seleccionada no pertenece a la sede activa.']);
        }
        if (! $series->is_active) {
            throw ValidationException::withMessages(['document_series_id' => 'La serie seleccionada se encuentra inactiva.']);
        }

        // 2. Validar Almacén
        $warehouseId = $data['warehouse_id'] ?? null;
        if ($warehouseId !== null) {
            $warehouse = Warehouse::whereKey($warehouseId)->firstOrFail();
            if ($warehouse->branch_id !== $activeBranch->id || ! $warehouse->is_active) {
                throw ValidationException::withMessages(['warehouse_id' => 'El almacén no pertenece a la sede activa o se encuentra inactivo.']);
            }
        } else {
            $warehouse = Warehouse::where('branch_id', $activeBranch->id)->where('is_active', true)->first();
            if ($warehouse === null) {
                throw ValidationException::withMessages(['warehouse_id' => 'No hay almacenes activos disponibles en la sede.']);
            }
            $warehouseId = $warehouse->id;
        }

        // 3. Validar Sesión de Caja
        $cashSessionId = $data['cash_session_id'] ?? null;
        $cashSession = null;
        if ($cashSessionId !== null) {
            /** @var CashSession $cashSession */
            $cashSession = CashSession::with('box')->whereKey($cashSessionId)->first();
            if ($cashSession === null) {
                throw ValidationException::withMessages(['cash_session_id' => 'La sesión de caja seleccionada no existe.']);
            }
            if ($cashSession->status !== CashSessionStatus::Open) {
                throw ValidationException::withMessages(['cash_session_id' => 'La sesión de caja no se encuentra abierta.']);
            }
            if ($cashSession->box?->branch_id !== $activeBranch->id) {
                throw ValidationException::withMessages(['cash_session_id' => 'La caja de la sesión no pertenece a la sede activa.']);
            }
            if (! $this->boxAccessService->canOperate($user, $cashSession->box)) {
                throw ValidationException::withMessages(['cash_session_id' => 'No tiene autorización para operar esta caja.']);
            }
            if ($series->box_id !== null && $series->box_id !== $cashSession->box_id) {
                throw ValidationException::withMessages(['document_series_id' => 'La serie seleccionada está asignada a otra caja.']);
            }
        }

        // 4. Validar Cliente
        if (empty($data['client_id'])) {
            throw ValidationException::withMessages(['client_id' => 'Debe seleccionar un cliente.']);
        }
        /** @var Client $client */
        $client = Client::whereKey($data['client_id'])->firstOrFail();
        if (! $client->is_active) {
            throw ValidationException::withMessages(['client_id' => 'El cliente seleccionado se encuentra inactivo.']);
        }

        // 5. Validar Moneda
        $currencyId = $data['currency_id'] ?? null;
        /** @var Currency $currency */
        $currency = $currencyId ? Currency::whereKey($currencyId)->firstOrFail() : Currency::base();
        if ($currency === null || ! $currency->is_active) {
            throw ValidationException::withMessages(['currency_id' => 'La moneda seleccionada no está disponible.']);
        }

        // 6. Validar Fecha de Emisión
        $today = Carbon::today();
        $issueDate = isset($data['issue_date']) ? Carbon::parse($data['issue_date'])->startOfDay() : $today->copy();
        if ($issueDate->gt($today)) {
            throw ValidationException::withMessages(['issue_date' => 'No se permite emitir comprobantes con fecha futura.']);
        }
        if ($issueDate->lt($today)) {
            if (! $user->can('sales.backdate')) {
                throw ValidationException::withMessages(['issue_date' => 'No tiene autorización para emitir comprobantes con fecha retroactiva.']);
            }
            $maxBackdateDays = (int) $this->parameterService->get('sales.max_backdate_days', 3);
            if ($issueDate->diffInDays($today) > $maxBackdateDays) {
                throw ValidationException::withMessages([
                    'issue_date' => "No se permite emitir comprobantes con más de {$maxBackdateDays} días de antigüedad.",
                ]);
            }
        }

        // 7. Validar Cotización (si aplica)
        /** @var Quotation|null $quotation */
        $quotation = null;
        if (! empty($data['quotation_id'])) {
            $quotation = Quotation::whereKey($data['quotation_id'])->firstOrFail();
            if ($quotation->branch_id !== $activeBranch->id) {
                throw ValidationException::withMessages(['quotation_id' => 'La cotización no pertenece a la sede activa.']);
            }
            if ($quotation->client_id !== $client->id) {
                throw ValidationException::withMessages(['quotation_id' => 'La cotización no pertenece al cliente seleccionado.']);
            }
            if (! in_array($quotation->status, [QuotationStatus::Sent, QuotationStatus::Accepted], true)) {
                throw ValidationException::withMessages(['quotation_id' => "La cotización no se encuentra en estado vigente o aceptada (estado actual: {$quotation->status->label()})."]);
            }
        }

        // 8. Procesar Líneas, Precios y Descuentos
        $itemsData = $data['items'] ?? [];
        if (empty($itemsData)) {
            throw ValidationException::withMessages(['items' => 'La venta debe contener al menos un producto o servicio.']);
        }

        $priceTypeStr = (string) ($data['price_type'] ?? 'retail');
        $priceType = PriceType::tryFrom($priceTypeStr) ?? PriceType::Retail;
        $pricesIncludeTax = filter_var($data['prices_include_tax'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $taxRate = '18.00';
        $globalDiscountPercent = (string) ($data['global_discount_percent'] ?? '0.00');

        $lineInputs = [];
        $preparedItems = [];

        foreach ($itemsData as $idx => $rawItem) {
            $presentationId = $rawItem['item_presentation_id'] ?? null;
            $quantity = (string) ($rawItem['quantity'] ?? '1');
            $discountPercent = (string) ($rawItem['discount_percent'] ?? '0.00');
            $customUnitPrice = isset($rawItem['unit_price']) ? (string) $rawItem['unit_price'] : null;

            if ($presentationId) {
                /** @var ItemPresentation $presentation */
                $presentation = ItemPresentation::with('catalogItem')->whereKey($presentationId)->firstOrFail();
                $catalogItem = $presentation->catalogItem;

                if (! $catalogItem || ! $catalogItem->is_active || ! $catalogItem->is_sellable) {
                    throw ValidationException::withMessages(["items.{$idx}" => 'El artículo no está disponible para la venta.']);
                }

                $exchangeRate = (string) ($data['exchange_rate'] ?? '1');
                $listPrice = (string) ($this->pricing->listPrice($presentation, $priceType, (float) $quantity, $currency, $exchangeRate) ?? '0.00');
                $unitPrice = $customUnitPrice ?? $listPrice;
                $description = (string) ($rawItem['description'] ?? "{$catalogItem->name} - {$presentation->name}");
                $taxType = $catalogItem->tax_type ?? TaxType::Taxable;
                $unitSunat = $catalogItem->baseUnit?->sunat_code;
                $baseQuantity = Decimal::mul($quantity, (string) $presentation->factor, 4);
            } else {
                $catalogItem = null;
                $presentation = null;
                $listPrice = $customUnitPrice ?? '0.00';
                $unitPrice = $listPrice;
                $description = (string) ($rawItem['description'] ?? 'Servicio / Concepto libre');
                $taxType = TaxType::tryFrom((string) ($rawItem['tax_type'] ?? 'taxable')) ?? TaxType::Taxable;
                $unitSunat = 'ZZ';
                $baseQuantity = $quantity;
            }

            $effectiveDiscount = $this->discountPolicy->calculateEffectiveDiscount($listPrice, $unitPrice, $discountPercent);

            $lineInput = new LineInput(
                quantity: $quantity,
                unitPrice: $unitPrice,
                discountPercent: $discountPercent,
                taxType: $taxType,
            );

            $lineInputs[] = $lineInput;
            $preparedItems[] = [
                'catalog_item' => $catalogItem,
                'presentation' => $presentation,
                'description' => $description,
                'unit_sunat_code' => $unitSunat,
                'base_quantity' => $baseQuantity,
                'quantity' => $quantity,
                'list_price' => $listPrice,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'effective_discount_percent' => (string) $effectiveDiscount,
                'tax_type' => $taxType,
            ];
        }

        // Validar política de descuentos
        $linesForDiscount = array_map(fn (array $p): array => [
            'list_price' => $p['list_price'],
            'unit_price' => $p['unit_price'],
            'discount_percent' => $p['discount_percent'],
        ], $preparedItems);

        $overrideUser = $this->discountPolicy->validate($linesForDiscount, $globalDiscountPercent, $user);

        // Calcular totales
        $calculator = new DocumentTotalsCalculator;
        $totals = $calculator->calculate($lineInputs, $taxRate, $pricesIncludeTax, $globalDiscountPercent);

        // 9. Validar Reglas Fiscales del Comprobante
        if ($series->document_type === SalesDocumentType::Invoice) {
            if ($client->document_type !== DocumentType::Ruc || strlen(trim((string) $client->document_number)) !== 11) {
                throw ValidationException::withMessages([
                    'client_id' => 'Para emitir una Factura el cliente debe contar con un número de RUC válido de 11 dígitos.',
                ]);
            }
        } elseif ($series->document_type === SalesDocumentType::Receipt) {
            $threshold = (float) $this->parameterService->get('sales.receipt_identity_threshold', 700.00);
            if ((float) $totals->total >= $threshold) {
                if ($client->is_system || empty($client->document_number)) {
                    throw ValidationException::withMessages([
                        'client_id' => "Para boletas con importe igual o mayor a S/ {$threshold} el cliente debe estar debidamente identificado con documento.",
                    ]);
                }
            }
        }

        // 10. Validar Condición de Pago y Cobros
        $conditionStr = (string) ($data['payment_condition'] ?? 'cash');
        $condition = PaymentCondition::from($conditionStr);
        $paymentsInput = $data['payments'] ?? [];

        $sumPayments = '0.00';
        foreach ($paymentsInput as $p) {
            $sumPayments = Decimal::add($sumPayments, (string) ($p['amount'] ?? '0.00'), 2);
        }

        if ($condition === PaymentCondition::Cash) {
            if (empty($paymentsInput)) {
                throw ValidationException::withMessages([
                    'payments' => 'Debe registrar al menos un método de pago para ventas al contado.',
                ]);
            }
            if (Decimal::cmp($sumPayments, $totals->total, 2) !== 0) {
                throw ValidationException::withMessages([
                    'payments' => "La suma de pagos (S/ {$sumPayments}) debe ser igual al total del comprobante (S/ {$totals->total}).",
                ]);
            }

            // Validar monto recibido en efectivo
            foreach ($paymentsInput as $idx => $p) {
                /** @var PaymentMethod $pm */
                $pm = PaymentMethod::findOrFail($p['payment_method_id']);
                if ($pm->settlement === Settlement::CashBox && $cashSessionId === null) {
                    throw ValidationException::withMessages([
                        'cash_session_id' => 'Se requiere una sesión de caja abierta para recibir pagos en efectivo.',
                    ]);
                }
                if ($pm->kind === PaymentMethodKind::Cash && isset($p['received_amount'])) {
                    if (Decimal::cmp((string) $p['received_amount'], (string) $p['amount'], 2) < 0) {
                        throw ValidationException::withMessages([
                            "payments.{$idx}.received_amount" => 'El monto recibido en efectivo no puede ser menor al monto a cobrar.',
                        ]);
                    }
                }
            }

            $paidAmount = $totals->total;
            $balance = '0.00';
            $dueDate = null;
        } else {
            // Crédito
            if (! $user->can('sales.sell_on_credit')) {
                throw ValidationException::withMessages([
                    'payment_condition' => 'No tiene autorización para emitir ventas al crédito.',
                ]);
            }

            if (Decimal::cmp($sumPayments, $totals->total, 2) > 0) {
                throw ValidationException::withMessages([
                    'payments' => 'Los pagos a cuenta no pueden superar el total de la venta.',
                ]);
            }

            $paidAmount = $sumPayments;
            $balance = Decimal::sub($totals->total, $paidAmount, 2);

            // Validar línea disponible
            $this->creditPolicy->assertAvailable($client, $balance);

            $creditDays = $client->credit_days > 0 ? $client->credit_days : 30;
            $dueDate = ! empty($data['due_date'])
                ? Carbon::parse($data['due_date'])->toDateString()
                : $issueDate->copy()->addDays($creditDays)->toDateString();
        }

        // 11. Ejecución Transaccional Atómica
        return DB::transaction(function () use (
            $data,
            $user,
            $activeBranch,
            $warehouseId,
            $cashSessionId,
            $series,
            $client,
            $currency,
            $issueDate,
            $dueDate,
            $quotation,
            $priceType,
            $pricesIncludeTax,
            $taxRate,
            $globalDiscountPercent,
            $preparedItems,
            $totals,
            $condition,
            $paidAmount,
            $balance,
            $paymentsInput,
            $overrideUser
        ): Sale {
            // A. Obtener el siguiente correlativo atómico con bloqueo
            $seq = $this->seriesService->next($series);
            $saleNumber = $seq['number'];
            $fullNumber = $seq['full'];

            // B. Crear Comprobante de Venta con datos de cliente congelados
            $sale = Sale::create([
                'branch_id' => $activeBranch->id,
                'warehouse_id' => $warehouseId,
                'cash_session_id' => $cashSessionId,
                'document_series_id' => $series->id,
                'document_type' => $series->document_type,
                'series' => $series->series,
                'number' => $saleNumber,
                'full_number' => $fullNumber,
                'issue_date' => $issueDate->toDateString(),
                'issued_at' => now(),
                'due_date' => $dueDate,
                'client_id' => $client->id,
                'client_document_type' => $client->document_type?->value,
                'client_document_number' => $client->document_number,
                'client_name' => $client->name,
                'client_address' => $client->address,
                'seller_id' => $data['seller_id'] ?? null,
                'quotation_id' => $quotation?->id,
                'currency_id' => $currency->id,
                'exchange_rate' => $data['exchange_rate'] ?? '1.0000',
                'price_type' => $priceType,
                'prices_include_tax' => $pricesIncludeTax,
                'tax_rate' => $taxRate,
                'global_discount_percent' => $globalDiscountPercent,
                'taxable_base' => $totals->taxableBase,
                'exempt_base' => $totals->exemptBase,
                'unaffected_base' => $totals->unaffectedBase,
                'discount_total' => $totals->discountTotal,
                'tax_total' => $totals->taxTotal,
                'total' => $totals->total,
                'total_base_currency' => $totals->total,
                'cost_total' => '0.0000',
                'payment_condition' => $condition,
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'status' => SaleStatus::Issued,
                'einvoice_status' => $series->is_electronic ? EInvoiceStatus::Pending : EInvoiceStatus::NotApplicable,
                'discount_override_by' => $overrideUser?->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            // C. Crear Líneas y aplicar Kardex
            $totalCost = '0.0000';

            foreach ($preparedItems as $idx => $pItem) {
                $lineResult = $totals->lines[$idx];
                $catalogItem = $pItem['catalog_item'];
                $presentation = $pItem['presentation'];

                $unitCost = '0.000000';
                $lineCostTotal = '0.0000';

                // Si controla inventario, descargar stock vía StockLedgerService
                if ($catalogItem !== null && $catalogItem->track_stock) {
                    $kardex = $this->stockLedger->out(new StockMovementData(
                        warehouseId: $sale->warehouse_id,
                        catalogItemId: $catalogItem->id,
                        baseQuantity: (float) $pItem['base_quantity'],
                        type: MovementType::Sale,
                        source: $sale,
                        sourceNumber: $sale->full_number,
                        presentationId: $presentation?->id,
                        presentationQuantity: (float) $pItem['quantity'],
                        reasonCode: 'SALE',
                        notes: "Venta {$sale->full_number}",
                        movedAt: $sale->issued_at,
                    ));

                    $unitCost = number_format((float) $kardex->unit_cost, 6, '.', '');
                    $lineCostTotal = number_format((float) $kardex->total_cost, 4, '.', '');
                    $totalCost = Decimal::add($totalCost, $lineCostTotal, 4);
                }

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'line' => $idx + 1,
                    'catalog_item_id' => $catalogItem?->id,
                    'item_presentation_id' => $presentation?->id,
                    'description' => $pItem['description'],
                    'unit_sunat_code' => $pItem['unit_sunat_code'],
                    'quantity' => $pItem['quantity'],
                    'base_quantity' => $pItem['base_quantity'],
                    'list_price' => $pItem['list_price'],
                    'unit_price' => $pItem['unit_price'],
                    'discount_percent' => $pItem['discount_percent'],
                    'effective_discount_percent' => $pItem['effective_discount_percent'],
                    'tax_type' => $pItem['tax_type'],
                    'line_subtotal' => $lineResult->subtotal,
                    'line_tax' => $lineResult->tax,
                    'line_total' => $lineResult->total,
                    'unit_cost' => $unitCost,
                    'cost_total' => $lineCostTotal,
                ]);
            }

            $sale->update(['cost_total' => $totalCost]);

            // D. Registrar Cobros con MoneySettlementService
            foreach ($paymentsInput as $p) {
                $amount = (string) $p['amount'];
                $received = isset($p['received_amount']) ? (string) $p['received_amount'] : null;
                $change = ($received !== null && Decimal::cmp($received, $amount, 2) >= 0)
                    ? Decimal::sub($received, $amount, 2)
                    : '0.00';

                $salePayment = SalePayment::create([
                    'sale_id' => $sale->id,
                    'payment_method_id' => $p['payment_method_id'],
                    'cash_session_id' => $p['cash_session_id'] ?? $cashSessionId,
                    'amount' => $amount,
                    'received_amount' => $received,
                    'change_amount' => $change,
                    'reference' => $p['reference'] ?? null,
                    'status' => 'completed',
                ]);

                $this->settlement->settle(new SettlementData(
                    paymentMethodId: $salePayment->payment_method_id,
                    direction: MoneyDirection::In,
                    amount: $amount,
                    currencyId: $sale->currency_id,
                    source: $salePayment,
                    sourceNumber: $sale->full_number,
                    branchId: $sale->branch_id,
                    cashSessionId: $salePayment->cash_session_id,
                    concept: SystemConcept::SaleCollection,
                    reference: $salePayment->reference,
                    description: "Cobro venta {$sale->full_number} - {$sale->client_name}",
                    counterpartyName: $sale->client_name,
                    movementDate: $sale->issued_at,
                    user: $user,
                ));
            }

            // E. Si proviene de cotización, marcar como convertida
            if ($quotation !== null) {
                $this->quotationWorkflow->markConverted($quotation, $sale);
            }

            // F. Ejecutar hooks posteriores a la emisión (dentro de la transacción)
            foreach ($this->afterIssueHooks as $hook) {
                $hook->handle($sale, $data);
            }

            // G. Despachar eventos y jobs después del commit
            DB::afterCommit(function () use ($sale): void {
                event(new SaleIssued($sale));

                if ($sale->einvoice_status === EInvoiceStatus::Pending) {
                    SendElectronicDocumentJob::dispatch($sale->id)->afterCommit();
                }
            });

            return $sale->fresh(['items', 'payments', 'series', 'client', 'seller', 'warehouse', 'currency']) ?? $sale;
        });
    }
}
