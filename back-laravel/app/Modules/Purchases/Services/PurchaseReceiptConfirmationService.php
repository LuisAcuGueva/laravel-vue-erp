<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Inventory\Data\StockMovementData;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\SystemReason;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Services\StockLedgerService;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Enums\PurchaseReceiptStatus;
use App\Modules\Purchases\Exceptions\PurchasesRuleException;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseReceipt;
use App\Modules\Users\Models\User;
use App\Support\Pricing\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseReceiptConfirmationService
{
    public function __construct(
        private readonly PurchaseReceiptService $receiptService,
        private readonly StockLedgerService $stockLedger,
        private readonly PurchaseOrderWorkflowService $orderWorkflow,
        private readonly ProviderPriceService $providerPriceService,
        private readonly SystemParameterService $parameterService,
        private readonly SupplierInvoiceService $supplierInvoiceService,
    ) {}

    /**
     * Confirma la recepción ingresando stock al ledger y actualizando la OC y precios.
     *
     * @param  list<array{due_date: string, amount: string|float}>|null  $installments  cronograma de cuotas
     *                                                                                  opcional para la factura del proveedor que se genera automáticamente (spec 034); `null` o vacío usa la
     *                                                                                  cuota única por defecto.
     * @param  array{amount: string|float, payment_method_id: string, cash_session_id?: string|null, reference?: string|null, payment_date?: string|null}|null  $downPayment
     *                                                                                                                                                                        pago mixto opcional (spec 035): monto que se paga en el acto; el saldo se reparte en `$installments`.
     * @return array{receipt: PurchaseReceipt, warnings: list<string>}
     *
     * @throws PurchasesRuleException
     */
    public function confirm(PurchaseReceipt $receipt, User $user, ?array $installments = null, ?array $downPayment = null): array
    {
        return DB::transaction(function () use ($receipt, $user, $installments, $downPayment): array {
            /** @var PurchaseReceipt $lockedReceipt */
            $lockedReceipt = PurchaseReceipt::query()
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReceipt->status !== PurchaseReceiptStatus::Draft) {
                throw new PurchasesRuleException('Solo se pueden confirmar recepciones en estado borrador', 'status');
            }

            /** @var PurchaseOrder $order */
            $order = PurchaseOrder::query()
                ->whereKey($lockedReceipt->purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $orderItems = $order->items()
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $tolerance = (float) ($this->parameterService->get('purchases.over_receipt_tolerance_percent') ?? 0.0);
            $multiplier = (string) (1 + ($tolerance / 100));

            $items = $lockedReceipt->items()
                ->with(['catalogItem', 'presentation'])
                ->get();

            // 1. Revalidar saldos pendientes
            foreach ($items as $item) {
                /** @var PurchaseOrderItem|null $orderItem */
                $orderItem = $orderItems->get($item->purchase_order_item_id);

                if ($orderItem === null) {
                    throw new PurchasesRuleException('El ítem no pertenece a la orden de compra', 'items');
                }

                $pending = (string) $orderItem->pending_quantity;
                $maxAllowed = Decimal::cmp($pending, '0', 4) > 0
                    ? Decimal::mul($pending, $multiplier, 4)
                    : '0.0000';

                if (Decimal::cmp((string) $item->quantity, $maxAllowed, 4) > 0) {
                    throw new PurchasesRuleException(
                        "La cantidad a recibir ({$item->quantity}) excede el saldo pendiente actual ({$maxAllowed}) para «{$orderItem->description}»",
                        'items'
                    );
                }
            }

            // 2. Preparar y registrar movimientos de stock en lote para productos con inventario
            $stockMovements = [];
            $receiptDate = Carbon::parse($lockedReceipt->receipt_date)->setTimeFrom(now());

            foreach ($items as $item) {
                if ($item->is_service || $item->catalog_item_id === null) {
                    continue;
                }

                /** @var CatalogItem|null $catalogItem */
                $catalogItem = $item->catalogItem;
                if ($catalogItem === null || ! $catalogItem->track_stock) {
                    continue;
                }

                $stockMovements[] = new StockMovementData(
                    warehouseId: $lockedReceipt->warehouse_id,
                    catalogItemId: $item->catalog_item_id,
                    baseQuantity: (float) ($item->base_quantity ?? $item->quantity),
                    type: MovementType::Purchase,
                    source: $lockedReceipt,
                    sourceNumber: $lockedReceipt->number,
                    unitCost: $item->base_unit_cost !== null ? (float) $item->base_unit_cost : null,
                    presentationId: $item->item_presentation_id,
                    presentationQuantity: (float) $item->quantity,
                    reasonCode: SystemReason::Purchase->value,
                    notes: "Recepción de compra {$lockedReceipt->number}",
                    movedAt: $receiptDate,
                );
            }

            if (! empty($stockMovements)) {
                $this->stockLedger->applyBatch($stockMovements);
            }

            // 3. Incrementar received_quantity en las líneas de la orden
            foreach ($items as $item) {
                /** @var PurchaseOrderItem $orderItem */
                $orderItem = $orderItems->get($item->purchase_order_item_id);
                $newReceived = Decimal::add((string) $orderItem->received_quantity, (string) $item->quantity, 4);
                $orderItem->update(['received_quantity' => $newReceived]);
            }

            // 4. Actualizar estado de la orden de compra
            $this->orderWorkflow->markReceived($order);

            // 5. Actualizar catalog_item.last_purchase_cost y último precio del proveedor
            $provider = $lockedReceipt->provider;
            $currency = $order->currency;

            foreach ($items as $item) {
                if ($item->is_service) {
                    continue;
                }

                if ($item->catalogItem !== null && $item->base_unit_cost !== null) {
                    $item->catalogItem->update([
                        'last_purchase_cost' => $item->base_unit_cost,
                    ]);
                }

                if ($item->presentation !== null && $provider !== null && $currency !== null) {
                    /** @var PurchaseOrderItem $orderItem */
                    $orderItem = $orderItems->get($item->purchase_order_item_id);
                    $netUnitPrice = (float) $orderItem->unit_price * (1 - ((float) $orderItem->discount_percent / 100));

                    if ($order->prices_include_tax && $orderItem->tax_type === TaxType::Taxable) {
                        $taxRate = (float) $order->tax_rate;
                        $netUnitPrice = $taxRate > 0 ? $netUnitPrice / (1 + ($taxRate / 100)) : $netUnitPrice;
                    }

                    $netPriceStr = number_format($netUnitPrice, 4, '.', '');
                    $this->providerPriceService->recordPurchase(
                        $provider,
                        $item->presentation,
                        $currency,
                        $netPriceStr,
                        $receiptDate
                    );
                }
            }

            // 6. Marcar recepción como confirmada
            $lockedReceipt->update([
                'status' => PurchaseReceiptStatus::Confirmed,
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
            ]);

            // 7. Si la recepción ya trae la factura del proveedor, generarla (o fusionarla) automáticamente.
            $warnings = $this->supplierInvoiceService->createOrAttachFromConfirmedReceipt($lockedReceipt, $user, $installments, $downPayment);

            return [
                'receipt' => $this->receiptService->findById($lockedReceipt->id) ?? $lockedReceipt,
                'warnings' => $warnings,
            ];
        });
    }

    /**
     * Recepción rápida del total pendiente de una orden de compra en un solo paso.
     *
     * @param  array<string, mixed>  $header
     * @return array{receipt: PurchaseReceipt, warnings: list<string>}
     *
     * @throws PurchasesRuleException
     */
    public function receiveAll(PurchaseOrder $order, array $header, User $user): array
    {
        return DB::transaction(function () use ($order, $header, $user): array {
            /** @var PurchaseOrder $lockedOrder */
            $lockedOrder = PurchaseOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true)) {
                throw new PurchasesRuleException(
                    'Solo se pueden recibir órdenes aprobadas o parcialmente recibidas',
                    'status'
                );
            }

            $orderItems = $lockedOrder->items()->lockForUpdate()->get();
            $itemsData = [];

            foreach ($orderItems as $orderItem) {
                if (Decimal::cmp((string) $orderItem->pending_quantity, '0', 4) > 0) {
                    $itemsData[] = [
                        'purchase_order_item_id' => $orderItem->id,
                        'quantity' => (string) $orderItem->pending_quantity,
                        'notes' => null,
                    ];
                }
            }

            if (empty($itemsData)) {
                throw new PurchasesRuleException('La orden de compra no tiene ítems pendientes por recibir', 'items');
            }

            $receiptData = array_merge($header, [
                'purchase_order_id' => $lockedOrder->id,
                'warehouse_id' => $header['warehouse_id'] ?? $lockedOrder->warehouse_id,
                'items' => $itemsData,
            ]);

            $receipt = $this->receiptService->create($receiptData, $user);

            return $this->confirm($receipt, $user, $header['installments'] ?? null, $header['down_payment'] ?? null);
        });
    }

    /**
     * Anula una recepción (revirtiendo stock y orden si estaba confirmada).
     *
     * @throws PurchasesRuleException
     */
    public function cancel(PurchaseReceipt $receipt, string $reason, User $user): PurchaseReceipt
    {
        return DB::transaction(function () use ($receipt, $reason, $user): PurchaseReceipt {
            /** @var PurchaseReceipt $lockedReceipt */
            $lockedReceipt = PurchaseReceipt::query()
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReceipt->status === PurchaseReceiptStatus::Cancelled) {
                throw new PurchasesRuleException('La recepción ya se encuentra anulada', 'status');
            }

            if ($this->isInvoiced($lockedReceipt)) {
                throw new PurchasesRuleException('Anule primero la factura del proveedor vinculada a esta recepción', 'status');
            }

            if ($lockedReceipt->status === PurchaseReceiptStatus::Confirmed) {
                // 1. Revertir cada movimiento de kardex asociado
                $kardexMovements = $lockedReceipt->kardexMovements()->get();
                foreach ($kardexMovements as $movement) {
                    $this->stockLedger->reverse($movement, $lockedReceipt, $lockedReceipt->number);
                }

                // 2. Decrementar received_quantity en las líneas de la orden
                /** @var PurchaseOrder $order */
                $order = PurchaseOrder::query()
                    ->whereKey($lockedReceipt->purchase_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $orderItems = $order->items()
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $receiptItems = $lockedReceipt->items()->get();
                foreach ($receiptItems as $item) {
                    /** @var PurchaseOrderItem|null $orderItem */
                    $orderItem = $orderItems->get($item->purchase_order_item_id);
                    if ($orderItem !== null) {
                        $remaining = Decimal::sub((string) $orderItem->received_quantity, (string) $item->quantity, 4);
                        if (Decimal::cmp($remaining, '0', 4) < 0) {
                            $remaining = '0.0000';
                        }
                        $orderItem->update(['received_quantity' => $remaining]);
                    }
                }

                // 3. Recalcular estado de la orden de compra (si no está cerrada)
                if ($order->status !== PurchaseOrderStatus::Closed) {
                    $this->orderWorkflow->markReceived($order);
                }
            }

            // 4. Marcar recepción como anulada
            $lockedReceipt->update([
                'status' => PurchaseReceiptStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $this->receiptService->findById($lockedReceipt->id) ?? $lockedReceipt;
        });
    }

    /**
     * Una recepción vinculada a una factura de proveedor no anulada no puede anularse (spec 024).
     */
    protected function isInvoiced(PurchaseReceipt $receipt): bool
    {
        return SupplierInvoiceService::isReceiptInvoiced($receipt->id);
    }
}
