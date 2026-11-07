<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Enums\PurchaseReceiptStatus;
use App\Modules\Purchases\Enums\SupplierInvoiceStatus;
use App\Modules\Purchases\Exceptions\PurchasesRuleException;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseReceipt;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use App\Support\Pricing\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseReceiptService
{
    public function __construct(
        private readonly SystemParameterService $parameterService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PurchasesRuleException
     */
    public function create(array $data, User $user, ?string $activeBranchId = null): PurchaseReceipt
    {
        return DB::transaction(function () use ($data, $user, $activeBranchId): PurchaseReceipt {
            /** @var PurchaseOrder $order */
            $order = PurchaseOrder::query()
                ->whereKey($data['purchase_order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($activeBranchId !== null && $order->branch_id !== $activeBranchId) {
                throw new PurchasesRuleException(
                    'La orden de compra no pertenece a la sede activa',
                    'purchase_order_id'
                );
            }

            if (! in_array($order->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true)) {
                throw new PurchasesRuleException(
                    'Solo se pueden registrar recepciones para órdenes de compra aprobadas o parcialmente recibidas',
                    'purchase_order_id'
                );
            }

            $warehouseId = $data['warehouse_id'] ?? $order->warehouse_id;
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::query()->whereKey($warehouseId)->firstOrFail();

            if ($warehouse->branch_id !== $order->branch_id) {
                throw new PurchasesRuleException(
                    'El almacén debe pertenecer a la misma sede de la orden de compra',
                    'warehouse_id'
                );
            }

            $tolerance = (float) ($this->parameterService->get('purchases.over_receipt_tolerance_percent') ?? 0.0);
            $orderItems = $order->items()->with(['presentation', 'catalogItem'])->get()->keyBy('id');

            /** @var list<array<string, mixed>> $itemsData */
            $itemsData = $data['items'];
            $calculatedItems = $this->calculateAndValidateItems($order, $orderItems, $itemsData, $tolerance);

            $number = SequentialCodeGenerator::next('purchase_receipt', 'RC');

            /** @var PurchaseReceipt $receipt */
            $receipt = PurchaseReceipt::query()->create([
                'number' => $number,
                'branch_id' => $order->branch_id,
                'purchase_order_id' => $order->id,
                'provider_id' => $order->provider_id,
                'warehouse_id' => $warehouseId,
                'receipt_date' => $data['receipt_date'],
                'provider_document_type' => $data['provider_document_type'],
                'provider_document_number' => $data['provider_document_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => PurchaseReceiptStatus::Draft,
                'total_cost' => $calculatedItems['total_cost'],
                'created_by' => $user->id,
            ]);

            foreach ($calculatedItems['lines'] as $lineData) {
                $receipt->items()->create($lineData);
            }

            return $this->findById($receipt->id) ?? $receipt;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PurchasesRuleException
     */
    public function update(PurchaseReceipt $receipt, array $data, User $user): PurchaseReceipt
    {
        return DB::transaction(function () use ($receipt, $data): PurchaseReceipt {
            /** @var PurchaseReceipt $lockedReceipt */
            $lockedReceipt = PurchaseReceipt::query()
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReceipt->status !== PurchaseReceiptStatus::Draft) {
                throw new PurchasesRuleException('Solo se pueden modificar recepciones en borrador', 'status');
            }

            /** @var PurchaseOrder $order */
            $order = PurchaseOrder::query()
                ->whereKey($lockedReceipt->purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $warehouseId = $data['warehouse_id'] ?? $lockedReceipt->warehouse_id;
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::query()->whereKey($warehouseId)->firstOrFail();

            if ($warehouse->branch_id !== $order->branch_id) {
                throw new PurchasesRuleException(
                    'El almacén debe pertenecer a la misma sede de la orden de compra',
                    'warehouse_id'
                );
            }

            $updateData = [
                'warehouse_id' => $warehouseId,
                'receipt_date' => $data['receipt_date'] ?? $lockedReceipt->receipt_date,
                'provider_document_type' => $data['provider_document_type'] ?? $lockedReceipt->provider_document_type,
                'provider_document_number' => array_key_exists('provider_document_number', $data)
                    ? $data['provider_document_number']
                    : $lockedReceipt->provider_document_number,
                'notes' => array_key_exists('notes', $data)
                    ? $data['notes']
                    : $lockedReceipt->notes,
            ];

            if (isset($data['items'])) {
                $tolerance = (float) ($this->parameterService->get('purchases.over_receipt_tolerance_percent') ?? 0.0);
                $orderItems = $order->items()->with(['presentation', 'catalogItem'])->get()->keyBy('id');

                /** @var list<array<string, mixed>> $itemsData */
                $itemsData = $data['items'];
                $calculated = $this->calculateAndValidateItems($order, $orderItems, $itemsData, $tolerance);

                $lockedReceipt->items()->delete();

                foreach ($calculated['lines'] as $lineData) {
                    $lockedReceipt->items()->create($lineData);
                }

                $updateData['total_cost'] = $calculated['total_cost'];
            }

            $lockedReceipt->update($updateData);

            return $this->findById($lockedReceipt->id) ?? $lockedReceipt;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingItems(PurchaseOrder $order): array
    {
        $order->loadMissing(['items.presentation', 'items.catalogItem']);

        $pending = [];
        foreach ($order->items as $orderItem) {
            $pendingQty = (string) $orderItem->pending_quantity;
            $unitCost = $this->calculateUnitCost($order, $orderItem);

            $pending[] = [
                'purchase_order_item_id' => $orderItem->id,
                'line' => $orderItem->line,
                'description' => $orderItem->description,
                'is_service' => $orderItem->is_service,
                'catalog_item' => $orderItem->catalogItem ? [
                    'id' => $orderItem->catalogItem->id,
                    'code' => $orderItem->catalogItem->code,
                    'name' => $orderItem->catalogItem->name,
                    'track_stock' => $orderItem->catalogItem->track_stock,
                ] : null,
                'presentation' => $orderItem->presentation ? [
                    'id' => $orderItem->presentation->id,
                    'name' => $orderItem->presentation->name,
                    'factor' => $orderItem->presentation->factor,
                ] : null,
                'ordered_quantity' => $orderItem->quantity,
                'received_quantity' => $orderItem->received_quantity,
                'pending_quantity' => $pendingQty,
                'unit_cost_preview' => number_format($unitCost, 4, '.', ''),
                'unit_price' => $orderItem->unit_price,
                'discount_percent' => $orderItem->discount_percent,
                'tax_type' => $orderItem->tax_type->value,
            ];
        }

        return $pending;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PurchaseReceipt>
     */
    public function query(string $branchId, array $filters = []): Builder
    {
        $query = PurchaseReceipt::query()
            ->where('branch_id', $branchId)
            ->with([
                'order:id,number,status,currency_id,total,order_date',
                'order.currency:id,code,symbol',
                'provider:id,document_type,document_number,business_name',
                'warehouse:id,name,code',
                'creator:id,name,email',
                'supplierInvoices:id,number,status,series,document_number,total',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['provider_id'])) {
            $query->where('provider_id', $filters['provider_id']);
        }

        if (! empty($filters['purchase_order_id'])) {
            $query->where('purchase_order_id', $filters['purchase_order_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        // Recepciones aún sin factura de proveedor vigente (spec 024).
        if (! empty($filters['not_invoiced'])) {
            $query->whereDoesntHave('supplierInvoices', fn (Builder $q) => $q->where('supplier_invoices.status', '<>', SupplierInvoiceStatus::Cancelled->value));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('receipt_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('receipt_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('provider_document_number', 'like', "%{$search}%")
                    ->orWhereHas('provider', function (Builder $p) use ($search): void {
                        $p->where('business_name', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderByDesc('receipt_date')->orderByDesc('created_at');
    }

    public function findById(string $id): ?PurchaseReceipt
    {
        /** @var PurchaseReceipt|null */
        return PurchaseReceipt::query()
            ->with([
                'branch:id,name,code',
                'order:id,number,status,currency_id,total,exchange_rate,order_date',
                'order.currency:id,code,symbol',
                'provider:id,document_type,document_number,business_name,credit_days',
                'warehouse:id,name,code',
                'creator:id,name,email',
                'confirmer:id,name,email',
                'canceller:id,name,email',
                'items.orderItem:id,line,description,quantity,received_quantity,tax_type',
                'items.catalogItem:id,code,name,track_stock',
                'items.presentation:id,name,factor',
                'kardexMovements.warehouse:id,name,code',
                'kardexMovements.reason:id,name,code',
                'supplierInvoices:id,number,status,series,document_number,total',
            ])
            ->find($id);
    }

    /**
     * @param  Collection<string, PurchaseOrderItem>  $orderItems
     * @param  list<array<string, mixed>>  $itemsData
     * @return array{lines: list<array<string, mixed>>, total_cost: string}
     *
     * @throws PurchasesRuleException
     */
    private function calculateAndValidateItems(
        PurchaseOrder $order,
        Collection $orderItems,
        array $itemsData,
        float $tolerance
    ): array {
        $lines = [];
        $totalCost = '0.00';
        $lineNumber = 1;

        foreach ($itemsData as $row) {
            $poItemId = (string) $row['purchase_order_item_id'];
            /** @var PurchaseOrderItem|null $orderItem */
            $orderItem = $orderItems->get($poItemId);

            if ($orderItem === null || $orderItem->purchase_order_id !== $order->id) {
                throw new PurchasesRuleException('El ítem no pertenece a la orden de compra indicada', 'items');
            }

            $qty = (string) $row['quantity'];
            $pending = (string) $orderItem->pending_quantity;

            $multiplier = (string) (1 + ($tolerance / 100));
            $maxAllowed = Decimal::cmp($pending, '0', 4) > 0
                ? Decimal::mul($pending, $multiplier, 4)
                : '0.0000';

            if (Decimal::cmp($qty, $maxAllowed, 4) > 0) {
                throw new PurchasesRuleException(
                    "La cantidad ({$qty}) excede el saldo pendiente permitido ({$maxAllowed}) para el ítem «{$orderItem->description}»",
                    'items'
                );
            }

            $unitCost = $this->calculateUnitCost($order, $orderItem);
            $unitCostStr = number_format($unitCost, 4, '.', '');

            $baseQuantity = null;
            $baseUnitCostStr = null;

            if (! $orderItem->is_service && $orderItem->item_presentation_id !== null) {
                $presentation = $orderItem->presentation;
                $factor = $presentation !== null ? (float) $presentation->factor : 1.0;
                $baseUnitCost = $factor > 0 ? round($unitCost / $factor, 6) : $unitCost;
                $baseUnitCostStr = number_format($baseUnitCost, 6, '.', '');

                $baseQuantity = $presentation !== null
                    ? number_format($presentation->toBaseQuantity((float) $qty), 4, '.', '')
                    : number_format((float) $qty, 4, '.', '');
            }

            $lineTotal = round($unitCost * (float) $qty, 2);
            $lineTotalStr = number_format($lineTotal, 2, '.', '');
            $totalCost = Decimal::add($totalCost, $lineTotalStr, 2);

            $lines[] = [
                'line' => $lineNumber++,
                'purchase_order_item_id' => $orderItem->id,
                'catalog_item_id' => $orderItem->catalog_item_id,
                'item_presentation_id' => $orderItem->item_presentation_id,
                'is_service' => $orderItem->is_service,
                'quantity' => number_format((float) $qty, 4, '.', ''),
                'base_quantity' => $baseQuantity,
                'unit_cost' => $unitCostStr,
                'base_unit_cost' => $baseUnitCostStr,
                'total_cost' => $lineTotalStr,
                'notes' => $row['notes'] ?? null,
            ];
        }

        return [
            'lines' => $lines,
            'total_cost' => $totalCost,
        ];
    }

    /**
     * Calcula el costo unitario por presentación en moneda base y sin impuesto.
     */
    public function calculateUnitCost(PurchaseOrder $order, PurchaseOrderItem $item): float
    {
        $unitPrice = (float) $item->unit_price;
        $discountPercent = (float) $item->discount_percent;
        $netUnit = $unitPrice * (1 - ($discountPercent / 100));

        if ($order->prices_include_tax && $item->tax_type === TaxType::Taxable) {
            $taxRate = (float) $order->tax_rate;
            $netUnit = $taxRate > 0 ? $netUnit / (1 + ($taxRate / 100)) : $netUnit;
        }

        $exchangeRate = (float) $order->exchange_rate;

        return round($netUnit * $exchangeRate, 4);
    }
}
