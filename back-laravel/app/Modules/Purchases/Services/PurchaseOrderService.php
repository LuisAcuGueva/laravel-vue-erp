<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Configuration\Services\CompanySettingService;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Services\ExchangeRateService;
use App\Modules\Inventory\Enums\CatalogItemType;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\PaymentCondition;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Enums\PurchaseRequestStatus;
use App\Modules\Purchases\Exceptions\PurchasesRuleException;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseRequestItem;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use App\Support\Pricing\Decimal;
use App\Support\Pricing\DocumentTotalsCalculator;
use App\Support\Pricing\LineInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private readonly DocumentTotalsCalculator $calculator,
        private readonly CompanySettingService $companySettings,
        private readonly ExchangeRateService $exchangeRates,
        private readonly PurchaseRequestService $requestService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PurchaseOrder>
     */
    public function query(array $filters, string $branchId): Builder
    {
        $query = PurchaseOrder::query()
            ->with(['provider:id,document_type,document_number,business_name', 'currency:id,code,symbol', 'warehouse:id,code,name'])
            ->withCount('items')
            ->where('branch_id', $branchId);

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['provider_id'])) {
            $query->where('provider_id', (string) $filters['provider_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('order_date', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('order_date', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $inner) use ($like, $operator) {
                $inner->where('number', $operator, $like)
                    ->orWhereHas('provider', fn ($p) => $p->withTrashed()
                        ->where('business_name', $operator, $like)
                        ->orWhere('document_number', $operator, $like));
            });
        }

        return $query->orderByDesc('order_date')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function findById(string $id): ?PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with([
                'branch:id,code,name',
                'provider',
                'warehouse:id,code,name',
                'currency',
                'creator:id,name',
                'approver:id,name',
                'items.presentation:id,name,unit_id',
                'items.presentation.unit:id,name,symbol',
                'items.catalogItem:id,code,name',
                'items.requestItem.request:id,number',
                'receipts.warehouse:id,name,code',
                'receipts.creator:id,name',
                'receipts.supplierInvoices:id,number,status,series,document_number,total',
            ])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PurchasesRuleException
     */
    public function create(array $data, User $user, Branch $branch): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user, $branch): PurchaseOrder {
            $header = $this->buildHeader($data, $branch->id);
            $built = $this->buildLines($data['items'], $header['tax_rate'], $header['prices_include_tax']);

            $this->assertRequestQuantities($built['lines'], null);

            $order = PurchaseOrder::create(array_merge($header['attributes'], $built['totals'], [
                'number' => SequentialCodeGenerator::next('purchase_order', 'OC'),
                'branch_id' => $branch->id,
                'status' => PurchaseOrderStatus::Draft,
                'created_by' => $user->id,
            ]));

            $this->persistLines($order, $built['lines']);
            $this->requestService->refreshOrderedQuantities($this->requestItemIds($built['lines']));

            return $this->findById($order->id) ?? $order;
        });
    }

    /**
     * Edita una OC en borrador o rechazada; al editar una rechazada vuelve a borrador.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws PurchasesRuleException
     */
    public function update(PurchaseOrder $order, array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $data): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isEditable()) {
                throw new PurchasesRuleException('Solo se puede editar una orden en borrador o rechazada', 'status');
            }

            $header = $this->buildHeader($data, $locked->branch_id);
            $built = $this->buildLines($data['items'], $header['tax_rate'], $header['prices_include_tax']);

            $this->assertRequestQuantities($built['lines'], $locked);

            $previousIds = $locked->items()->pluck('purchase_request_item_id')->filter()->all();

            $locked->update(array_merge($header['attributes'], $built['totals'], [
                'status' => PurchaseOrderStatus::Draft,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]));

            $locked->items()->delete();
            $this->persistLines($locked, $built['lines']);

            $this->requestService->refreshOrderedQuantities(array_values(array_unique(array_merge(
                array_map('strval', $previousIds),
                $this->requestItemIds($built['lines']),
            ))));

            return $this->findById($locked->id) ?? $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{attributes: array<string, mixed>, tax_rate: string, prices_include_tax: bool}
     *
     * @throws PurchasesRuleException
     */
    private function buildHeader(array $data, string $branchId): array
    {
        /** @var Provider|null $provider */
        $provider = Provider::query()->find($data['provider_id']);
        if ($provider === null || ! $provider->is_active) {
            throw new PurchasesRuleException('El proveedor no existe o está inactivo', 'provider_id');
        }

        $inBranch = Warehouse::query()->whereKey($data['warehouse_id'])->where('branch_id', $branchId)->exists();
        if (! $inBranch) {
            throw new PurchasesRuleException('El almacén de entrega debe pertenecer a la sede activa', 'warehouse_id');
        }

        /** @var Currency $currency */
        $currency = Currency::query()->findOrFail($data['currency_id']);
        $rate = $this->resolveExchangeRate($currency, $data['exchange_rate'] ?? null, (string) $data['order_date']);

        $condition = PaymentCondition::from((string) $data['payment_condition']);
        $company = $this->companySettings->current();
        $includeTax = $data['prices_include_tax'] ?? (bool) $company->prices_include_tax;

        return [
            'attributes' => [
                'provider_id' => $provider->id,
                'warehouse_id' => $data['warehouse_id'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'currency_id' => $currency->id,
                'exchange_rate' => $rate,
                'payment_condition' => $condition,
                'credit_days' => $condition === PaymentCondition::Credit ? (int) $data['credit_days'] : 0,
                'prices_include_tax' => $includeTax,
                'tax_rate' => (string) $company->default_tax_rate,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ],
            'tax_rate' => (string) $company->default_tax_rate,
            'prices_include_tax' => (bool) $includeTax,
        ];
    }

    /**
     * @throws PurchasesRuleException
     */
    private function resolveExchangeRate(Currency $currency, mixed $given, string $date): string
    {
        $base = Currency::base();

        if ($base === null || $currency->id === $base->id) {
            return '1.0000';
        }

        if ($given !== null && (string) $given !== '') {
            return number_format((float) $given, 4, '.', '');
        }

        // Las compras se pagan al tipo de cambio de venta (el que cobra el banco por vender la divisa).
        $current = $this->exchangeRates->current($currency->id, Carbon::parse($date)->toDateString());
        if ($current['rate'] === null) {
            throw new PurchasesRuleException('Indica el tipo de cambio: no hay uno registrado para la moneda', 'exchange_rate');
        }

        return number_format((float) $current['rate']->sell_rate, 4, '.', '');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{lines: list<array<string, mixed>>, totals: array<string, string>}
     *
     * @throws PurchasesRuleException
     */
    private function buildLines(array $items, string $taxRate, bool $pricesIncludeTax): array
    {
        $requestItems = PurchaseRequestItem::query()
            ->whereIn('id', array_filter(array_column($items, 'purchase_request_item_id')))
            ->get()
            ->keyBy('id');

        $lines = [];
        $inputs = [];

        foreach ($items as $index => $item) {
            $presentationId = $item['item_presentation_id'] ?? null;
            $requestItem = isset($item['purchase_request_item_id']) ? $requestItems->get($item['purchase_request_item_id']) : null;
            $catalogItemId = null;
            $isService = false;
            $description = null;
            $defaultTax = TaxType::Taxable;

            if ($presentationId !== null) {
                $presentation = ItemPresentation::query()->with('item')->whereKey($presentationId)->first();
                $catalog = $presentation?->item;

                if ($presentation === null || $catalog === null || ! $catalog->is_active || ! $catalog->is_purchasable) {
                    throw new PurchasesRuleException('El artículo no está disponible para compra', "items.{$index}.item_presentation_id");
                }

                if ($requestItem?->catalog_item_id !== null && $requestItem->catalog_item_id !== $catalog->id) {
                    throw new PurchasesRuleException('El artículo no coincide con la línea del requerimiento', "items.{$index}.purchase_request_item_id");
                }

                $catalogItemId = $catalog->id;
                $isService = $catalog->type === CatalogItemType::Service;
                $defaultTax = $catalog->tax_type;
            } else {
                // Línea libre: solo para servicios, nunca para un producto del catálogo.
                if ($requestItem?->catalog_item_id !== null) {
                    throw new PurchasesRuleException('Un producto del catálogo debe indicarse con su presentación; la descripción libre es solo para servicios', "items.{$index}.description");
                }

                $isService = true;
                $description = (string) $item['description'];
            }

            $taxType = isset($item['tax_type']) ? TaxType::from((string) $item['tax_type']) : $defaultTax;

            $inputs[] = new LineInput(
                quantity: (string) $item['quantity'],
                unitPrice: (string) $item['unit_price'],
                discountPercent: (string) ($item['discount_percent'] ?? '0'),
                taxType: $taxType,
            );

            $lines[] = [
                'line' => $index + 1,
                'purchase_request_item_id' => $requestItem?->id,
                'catalog_item_id' => $catalogItemId,
                'item_presentation_id' => $presentationId,
                'description' => $description,
                'is_service' => $isService,
                'quantity' => (string) $item['quantity'],
                'unit_price' => (string) $item['unit_price'],
                'discount_percent' => (string) ($item['discount_percent'] ?? '0'),
                'tax_type' => $taxType,
            ];
        }

        $result = $this->calculator->calculate($inputs, $taxRate, $pricesIncludeTax);

        foreach ($result->lines as $i => $line) {
            $lines[$i]['line_subtotal'] = $line->subtotal;
            $lines[$i]['line_tax'] = $line->tax;
            $lines[$i]['line_total'] = $line->total;
        }

        return [
            'lines' => $lines,
            'totals' => [
                'taxable_base' => $result->taxableBase,
                'exempt_base' => $result->exemptBase,
                'unaffected_base' => $result->unaffectedBase,
                'discount_total' => $result->discountTotal,
                'tax_total' => $result->taxTotal,
                'total' => $result->total,
            ],
        ];
    }

    /**
     * Una línea vinculada no puede superar la cantidad pendiente del requerimiento (sumando todas las líneas que lo usan).
     *
     * @param  list<array<string, mixed>>  $lines
     *
     * @throws PurchasesRuleException
     */
    private function assertRequestQuantities(array $lines, ?PurchaseOrder $current): void
    {
        $requested = [];
        foreach ($lines as $index => $line) {
            $id = $line['purchase_request_item_id'];
            if ($id === null) {
                continue;
            }
            $requested[$id]['qty'] = Decimal::add($requested[$id]['qty'] ?? '0', (string) $line['quantity'], 4);
            $requested[$id]['index'] = $requested[$id]['index'] ?? $index;
        }

        if ($requested === []) {
            return;
        }

        $items = PurchaseRequestItem::query()->with('request')->whereIn('id', array_keys($requested))->lockForUpdate()->get()->keyBy('id');

        foreach ($requested as $id => $info) {
            /** @var PurchaseRequestItem|null $item */
            $item = $items->get($id);
            if ($item === null) {
                throw new PurchasesRuleException('La línea del requerimiento no existe', "items.{$info['index']}.purchase_request_item_id");
            }

            // Lo que esta misma OC ya reserva (al editar) no cuenta contra el saldo.
            $own = '0';
            if ($current !== null && $current->status->reservesRequestQuantity()) {
                $own = (string) PurchaseOrderItem::query()
                    ->where('purchase_order_id', $current->id)
                    ->where('purchase_request_item_id', $id)
                    ->sum('quantity');
            }

            // Un requerimiento ya totalmente ordenado sigue siendo editable por la OC que lo cubre.
            $editingOwnLines = Decimal::cmp($own, '0', 4) > 0 && $item->request->status === PurchaseRequestStatus::Ordered;
            if (! $item->request->status->isOpenForOrders() && ! $editingOwnLines) {
                throw new PurchasesRuleException('El requerimiento vinculado no está aprobado o ya fue atendido', "items.{$info['index']}.purchase_request_item_id");
            }

            $available = Decimal::add(Decimal::sub((string) $item->quantity, (string) $item->ordered_quantity, 4), $own, 4);

            if (Decimal::cmp($info['qty'], $available, 4) > 0) {
                throw new PurchasesRuleException("La cantidad supera lo pendiente del requerimiento ({$available})", "items.{$info['index']}.quantity");
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function persistLines(PurchaseOrder $order, array $lines): void
    {
        foreach ($lines as $line) {
            $order->items()->create($line);
        }

        $order->update([
            'total_base_currency' => Decimal::mul((string) $order->total, (string) $order->exchange_rate, 2),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<string>
     */
    private function requestItemIds(array $lines): array
    {
        return array_values(array_filter(array_map(
            fn (array $line): ?string => $line['purchase_request_item_id'] !== null ? (string) $line['purchase_request_item_id'] : null,
            $lines,
        )));
    }
}
