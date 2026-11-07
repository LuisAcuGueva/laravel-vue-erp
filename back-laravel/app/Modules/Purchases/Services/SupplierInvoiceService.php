<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Configuration\Services\CompanySettingService;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Services\ExchangeRateService;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\ProviderDocumentType;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Purchases\Enums\PurchaseReceiptStatus;
use App\Modules\Purchases\Enums\SupplierDocumentType;
use App\Modules\Purchases\Enums\SupplierInvoiceKind;
use App\Modules\Purchases\Enums\SupplierInvoiceStatus;
use App\Modules\Purchases\Enums\SupplierPaymentStatus;
use App\Modules\Purchases\Exceptions\PurchasesRuleException;
use App\Modules\Purchases\Models\PayableInstallment;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseReceipt;
use App\Modules\Purchases\Models\PurchaseReceiptItem;
use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Models\SupplierPaymentAllocation;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use App\Support\Pricing\Decimal;
use App\Support\Pricing\DocumentTotalsCalculator;
use App\Support\Pricing\LineInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SupplierInvoiceService
{
    private const ATTACHMENT_DIR = 'supplier-invoices';

    /** Estados de OC que ya admiten facturación. */
    private const INVOICEABLE_ORDER_STATUSES = [
        PurchaseOrderStatus::Approved,
        PurchaseOrderStatus::PartiallyReceived,
        PurchaseOrderStatus::Received,
        PurchaseOrderStatus::Closed,
    ];

    public function __construct(
        private readonly DocumentTotalsCalculator $calculator,
        private readonly CompanySettingService $companySettings,
        private readonly ExchangeRateService $exchangeRates,
        private readonly SystemParameterService $parameters,
        private readonly SupplierPaymentService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<SupplierInvoice>
     */
    public function query(array $filters, string $branchId): Builder
    {
        $query = SupplierInvoice::query()
            ->with(['provider:id,document_type,document_number,business_name', 'currency:id,code,symbol'])
            ->where('branch_id', $branchId);

        foreach (['kind', 'status', 'provider_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, (string) $filters[$field]);
            }
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('issue_date', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('issue_date', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $inner) use ($like, $operator) {
                $inner->where('number', $operator, $like)
                    ->orWhere('document_number', $operator, $like)
                    ->orWhere('counterparty_name', $operator, $like)
                    ->orWhereHas('provider', fn ($p) => $p->withTrashed()
                        ->where('business_name', $operator, $like)
                        ->orWhere('document_number', $operator, $like));
            });
        }

        return $query->orderByDesc('issue_date')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function findById(string $id): ?SupplierInvoice
    {
        return SupplierInvoice::query()
            ->with([
                'provider:id,document_type,document_number,business_name,credit_days',
                'currency:id,code,symbol',
                'branch:id,code,name',
                'order:id,number',
                'concept:id,code,name',
                'creator:id,name',
                'canceller:id,name',
                'items.catalogItem:id,code,name',
                'items.presentation:id,name',
                'installments',
                'receipts:id,number,receipt_date,status,total_cost',
                'allocations.payment:id,number,payment_date,amount,status',
            ])
            ->find($id);
    }

    /**
     * Registra una factura de proveedor (compra o gasto) con sus líneas, cuotas y recepciones.
     *
     * @param  array<string, mixed>  $data
     * @return array{invoice: SupplierInvoice, warnings: list<string>}
     *
     * @throws PurchasesRuleException
     */
    public function create(array $data, User $user, Branch $branch, ?UploadedFile $attachment = null): array
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($data, $user, $branch, $attachment, &$storedPath): array {
                $kind = SupplierInvoiceKind::from((string) $data['kind']);
                $provider = $this->resolveProvider($data, $kind);
                $documentType = $this->resolveDocumentType($data, $provider);
                $concept = $this->resolveConcept($data, $kind);

                /** @var Currency $currency */
                $currency = Currency::query()->findOrFail($data['currency_id']);
                $issueDate = Carbon::parse((string) $data['issue_date'])->startOfDay();

                $this->assertDocumentIsUnique($provider, $documentType, $data['series'] ?? null, (string) $data['document_number']);

                $order = $this->resolveOrder($data, $kind, $provider, $branch);
                $receipts = $this->lockReceipts($data['receipt_ids'] ?? [], $kind, $provider, $branch, $order, $currency);

                if ($order === null && $receipts->isNotEmpty() && $receipts->pluck('purchase_order_id')->unique()->count() === 1) {
                    $order = PurchaseOrder::query()->find($receipts->first()->purchase_order_id);
                }

                $company = $this->companySettings->current();
                $taxRate = (string) $company->default_tax_rate;
                $includeTax = array_key_exists('prices_include_tax', $data) && $data['prices_include_tax'] !== null
                    ? (bool) $data['prices_include_tax']
                    : (bool) $company->prices_include_tax;

                $receiptItems = $receipts->isEmpty()
                    ? new Collection
                    : PurchaseReceiptItem::query()->with('orderItem')->whereIn('purchase_receipt_id', $receipts->modelKeys())->get()->keyBy('id');

                $built = $this->buildLines(array_values((array) $data['items']), $receiptItems, $taxRate, $includeTax);
                $total = $built['totals']['total'];

                $dueDate = isset($data['due_date'])
                    ? Carbon::parse((string) $data['due_date'])->startOfDay()
                    : $issueDate->copy()->addDays($provider !== null ? $provider->credit_days : 0);

                $payNow = ! empty($data['pay_now']);
                $downPaymentAmount = null;
                if ($payNow) {
                    $downPaymentAmount = isset($data['down_payment_amount']) && $data['down_payment_amount'] !== ''
                        ? number_format((float) $data['down_payment_amount'], 2, '.', '')
                        : $total;
                }

                $installments = $this->buildInstallmentsWithDownPayment($data['installments'] ?? null, $downPaymentAmount, $total, $issueDate, $dueDate);
                $isMixedPayment = $downPaymentAmount !== null && Decimal::cmp($downPaymentAmount, $total, 2) < 0;

                $invoice = SupplierInvoice::create(array_merge($built['totals'], [
                    'number' => SequentialCodeGenerator::next('supplier_invoice', 'FP'),
                    'branch_id' => $branch->id,
                    'kind' => $kind,
                    'provider_id' => $provider?->id,
                    'counterparty_name' => $provider === null ? ($data['counterparty_name'] ?? null) : null,
                    'document_type' => $documentType,
                    'series' => $data['series'] ?? null,
                    'document_number' => (string) $data['document_number'],
                    'issue_date' => $issueDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'currency_id' => $currency->id,
                    'exchange_rate' => $this->resolveExchangeRate($currency, $data['exchange_rate'] ?? null, $issueDate->toDateString()),
                    'prices_include_tax' => $includeTax,
                    'tax_rate' => $taxRate,
                    'paid_amount' => '0.00',
                    'balance' => $total,
                    'status' => SupplierInvoiceStatus::Pending,
                    'purchase_order_id' => $order?->id,
                    'concept_id' => $concept?->id,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $user->id,
                ]));

                foreach ($built['lines'] as $line) {
                    $invoice->items()->create($line);
                }

                $allocations = [];
                $downPaymentInstallmentId = null;
                foreach ($installments as $index => $installment) {
                    $created = $invoice->installments()->create(array_merge($installment, [
                        'provider_id' => $provider?->id,
                        'currency_id' => $currency->id,
                        'paid_amount' => '0.00',
                        'balance' => $installment['amount'],
                        'status' => SupplierInvoiceStatus::Pending,
                    ]));
                    $allocations[] = ['installment_id' => $created->id, 'amount' => $installment['amount']];

                    if ($index === 0 && $isMixedPayment) {
                        $downPaymentInstallmentId = $created->id;
                    }
                }

                if ($receipts->isNotEmpty()) {
                    $invoice->receipts()->attach($receipts->modelKeys());
                }

                if ($attachment !== null) {
                    $stored = $attachment->store(self::ATTACHMENT_DIR, 'local');
                    if (is_string($stored)) {
                        $storedPath = $stored;
                        $invoice->forceFill(['attachment_path' => $stored])->save();
                    }
                }

                if ($payNow) {
                    /** @var array<string, mixed> $payment */
                    $payment = (array) ($data['payment'] ?? []);
                    $paymentAllocations = $isMixedPayment
                        ? [['installment_id' => $downPaymentInstallmentId, 'amount' => $downPaymentAmount]]
                        : $allocations;

                    $this->payments->create([
                        'provider_id' => $provider?->id,
                        'currency_id' => $currency->id,
                        'payment_method_id' => $payment['payment_method_id'] ?? null,
                        'cash_session_id' => $payment['cash_session_id'] ?? null,
                        'payment_date' => $payment['payment_date'] ?? today()->toDateString(),
                        'reference' => $payment['reference'] ?? null,
                        'notes' => $isMixedPayment
                            ? "Pago al contado de {$invoice->number}; el saldo queda financiado en cuotas."
                            : "Pago en el acto de {$invoice->number}",
                        'allocations' => $paymentAllocations,
                    ], $user, $branch);
                }

                $warnings = $receipts->isEmpty()
                    ? []
                    : $this->matchWarnings($total, $receiptItems, $order, $taxRate);

                return [
                    'invoice' => $this->findById($invoice->id) ?? $invoice,
                    'warnings' => $warnings,
                ];
            });
        } catch (Throwable $e) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $e;
        }
    }

    /**
     * Líneas sugeridas a partir de recepciones confirmadas, valorizadas al precio de la OC.
     *
     * @param  list<string>  $receiptIds
     * @return array{provider: array<string, mixed>|null, currency: array<string, mixed>|null, purchase_order: array<string, mixed>|null, prices_include_tax: bool|null, items: list<array<string, mixed>>}
     *
     * @throws PurchasesRuleException
     */
    public function prefill(array $receiptIds, string $branchId): array
    {
        $receipts = PurchaseReceipt::query()
            ->with(['provider:id,document_type,document_number,business_name,credit_days', 'order.currency:id,code,symbol', 'items.orderItem', 'items.catalogItem:id,code,name', 'items.presentation:id,name'])
            ->whereIn('id', $receiptIds)
            ->where('branch_id', $branchId)
            ->orderBy('receipt_date')
            ->orderBy('number')
            ->get();

        if ($receipts->count() !== count(array_unique($receiptIds))) {
            throw new PurchasesRuleException('Alguna de las recepciones no existe o no pertenece a la sede activa', 'receipt_ids');
        }

        $this->assertReceiptsInvoiceable($receipts);

        if ($receipts->pluck('provider_id')->unique()->count() > 1) {
            throw new PurchasesRuleException('Las recepciones deben ser del mismo proveedor', 'receipt_ids');
        }

        if ($receipts->map(fn (PurchaseReceipt $r) => $r->order->currency_id)->unique()->count() > 1) {
            throw new PurchasesRuleException('Las recepciones deben ser de órdenes en la misma moneda', 'receipt_ids');
        }

        $items = [];
        foreach ($receipts as $receipt) {
            foreach ($receipt->items as $item) {
                $orderItem = $item->orderItem;
                $items[] = [
                    'purchase_receipt_item_id' => $item->id,
                    'purchase_receipt_number' => $receipt->number,
                    'item_presentation_id' => $item->item_presentation_id,
                    'catalog_item' => $item->catalogItem !== null ? ['id' => $item->catalogItem->id, 'code' => $item->catalogItem->code, 'name' => $item->catalogItem->name] : null,
                    'presentation' => $item->presentation !== null ? ['id' => $item->presentation->id, 'name' => $item->presentation->name] : null,
                    'description' => $this->receiptItemDescription($item),
                    'quantity' => $item->quantity,
                    'unit_price' => $orderItem->unit_price,
                    'discount_percent' => $orderItem->discount_percent,
                    'tax_type' => $orderItem->tax_type->value,
                ];
            }
        }

        /** @var PurchaseReceipt|null $first */
        $first = $receipts->first();
        $orderIds = $receipts->pluck('purchase_order_id')->unique();
        $order = $first !== null && $orderIds->count() === 1 ? $first->order : null;

        return [
            'provider' => $first !== null ? [
                'id' => $first->provider->id,
                'document_type' => $first->provider->document_type->value,
                'document_number' => $first->provider->document_number,
                'business_name' => $first->provider->business_name,
                'credit_days' => $first->provider->credit_days,
            ] : null,
            'currency' => $first !== null ? [
                'id' => $first->order->currency->id,
                'code' => $first->order->currency->code,
                'symbol' => $first->order->currency->symbol,
            ] : null,
            'purchase_order' => $order !== null ? ['id' => $order->id, 'number' => $order->number] : null,
            'prices_include_tax' => $first?->order->prices_include_tax,
            'items' => $items,
        ];
    }

    /**
     * Anula una factura sin pagos vigentes; sus recepciones quedan libres para anularse o volver a facturarse.
     *
     * @throws PurchasesRuleException
     */
    public function cancel(SupplierInvoice $invoice, string $reason, User $user): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice, $reason, $user): SupplierInvoice {
            /** @var SupplierInvoice $locked */
            $locked = SupplierInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === SupplierInvoiceStatus::Cancelled) {
                throw new PurchasesRuleException('La factura ya se encuentra anulada', 'status');
            }

            $hasPayments = SupplierPaymentAllocation::query()
                ->where('supplier_invoice_id', $locked->id)
                ->whereHas('payment', fn ($q) => $q->where('status', SupplierPaymentStatus::Completed->value))
                ->exists();

            if ($hasPayments) {
                throw new PurchasesRuleException('Anule primero los pagos aplicados a esta factura', 'status');
            }

            PayableInstallment::query()
                ->where('supplier_invoice_id', $locked->id)
                ->update(['status' => SupplierInvoiceStatus::Cancelled->value, 'updated_at' => now()]);

            $locked->update([
                'status' => SupplierInvoiceStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $this->findById($locked->id) ?? $locked;
        });
    }

    /**
     * Una recepción está facturada si tiene vínculo con alguna factura no anulada.
     */
    public static function isReceiptInvoiced(string $receiptId): bool
    {
        return SupplierInvoice::query()
            ->where('status', '<>', SupplierInvoiceStatus::Cancelled->value)
            ->whereHas('receipts', fn ($q) => $q->where('purchase_receipts.id', $receiptId))
            ->exists();
    }

    /**
     * Genera (o fusiona en) la factura del proveedor al confirmar una recepción que ya trae el documento del
     * proveedor (`provider_document_type = invoice`). Por defecto nunca propaga errores: una recepción se confirma
     * siempre, aunque la factura no se pueda generar sola (p. ej. falta tipo de cambio registrado); en ese caso
     * queda pendiente de registrarse manualmente, como hoy.
     *
     * Si `$installments` o `$downPayment` vienen informados (el usuario los definió explícitamente al recibir,
     * specs 034/035), ese caso es la excepción: cualquier error (cuotas que no cuadran con el total, fecha
     * inválida, falta el método de pago, etc.) se propaga para que la confirmación de la recepción responda 422 —
     * es un dato que el propio usuario ingresó mal, no una condición externa.
     *
     * El pago mixto (`$downPayment`) solo se aplica cuando la recepción **crea** la factura; si se fusiona en una
     * ya existente, se ignora con una advertencia, igual que el cronograma personalizado.
     *
     * @param  list<array{due_date: string, amount: string|float}>|null  $installments
     * @param  array{amount: string|float, payment_method_id: string, cash_session_id?: string|null, reference?: string|null, payment_date?: string|null}|null  $downPayment
     * @return list<string> advertencias para mostrar al usuario (p. ej. cronograma o pago mixto ignorados por fusión)
     *
     * @throws PurchasesRuleException cuando `$installments` o `$downPayment` fueron indicados explícitamente y son inválidos
     */
    public function createOrAttachFromConfirmedReceipt(PurchaseReceipt $receipt, User $user, ?array $installments = null, ?array $downPayment = null): array
    {
        if ($receipt->provider_document_type !== ProviderDocumentType::Invoice) {
            return [];
        }

        $documentNumber = trim((string) $receipt->provider_document_number);
        if ($documentNumber === '' || self::isReceiptInvoiced($receipt->id)) {
            return [];
        }

        $hasCustomPlan = ! empty($installments) || ! empty($downPayment);
        $warnings = [];

        $run = function () use ($receipt, $user, $documentNumber, $installments, $downPayment, &$warnings): void {
            DB::transaction(function () use ($receipt, $user, $documentNumber, $installments, $downPayment, &$warnings): void {
                $prefill = $this->prefill([$receipt->id], $receipt->branch_id);

                /** @var SupplierInvoice|null $existing */
                $existing = SupplierInvoice::query()
                    ->where('provider_id', $receipt->provider_id)
                    ->where('document_type', SupplierDocumentType::Invoice->value)
                    ->whereRaw("COALESCE(series, '') = ''")
                    ->where('document_number', $documentNumber)
                    ->where('status', '<>', SupplierInvoiceStatus::Cancelled->value)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if (! empty($installments)) {
                        $warnings[] = "La factura {$existing->number} ya existe; el cronograma indicado no se aplicó, la recepción se agregó a su cuota vigente.";
                    }

                    if (! empty($downPayment)) {
                        $warnings[] = "La factura {$existing->number} ya existe; el pago al contado indicado no se aplicó, la recepción se agregó a su cuota vigente.";
                    }

                    $this->attachReceiptToInvoice($existing, $receipt, $prefill['items']);

                    return;
                }

                $this->create([
                    'kind' => SupplierInvoiceKind::Purchase->value,
                    'provider_id' => $receipt->provider_id,
                    'document_type' => SupplierDocumentType::Invoice->value,
                    'series' => null,
                    'document_number' => $documentNumber,
                    'issue_date' => $receipt->receipt_date->toDateString(),
                    'currency_id' => $prefill['currency']['id'] ?? null,
                    'prices_include_tax' => $prefill['prices_include_tax'],
                    'purchase_order_id' => $receipt->purchase_order_id,
                    'receipt_ids' => [$receipt->id],
                    'items' => $prefill['items'],
                    'installments' => $installments ?? [],
                    'pay_now' => ! empty($downPayment),
                    'down_payment_amount' => $downPayment['amount'] ?? null,
                    'payment' => $downPayment,
                    'notes' => "Generada automáticamente al confirmar la recepción {$receipt->number}.",
                ], $user, $receipt->branch);
            });
        };

        if ($hasCustomPlan) {
            $run();
        } else {
            try {
                $run();
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $warnings;
    }

    /**
     * Agrega las líneas de una recepción a una factura vigente del mismo documento de proveedor (spec pendiente:
     * un proveedor puede facturar varias recepciones parciales con un solo documento). Solo se fusiona cuando la
     * factura no tiene pagos ni cuotas personalizadas; en cualquier otro caso se deja para vincularse manualmente.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function attachReceiptToInvoice(SupplierInvoice $invoice, PurchaseReceipt $receipt, array $items): void
    {
        if (Decimal::cmp((string) $invoice->paid_amount, '0', 2) > 0) {
            return;
        }

        $installments = $invoice->installments()->lockForUpdate()->get();
        $installment = $installments->count() === 1 ? $installments->first() : null;
        if ($installment === null) {
            return;
        }

        $receiptItems = PurchaseReceiptItem::query()
            ->with('orderItem')
            ->where('purchase_receipt_id', $receipt->id)
            ->get()
            ->keyBy('id');

        if ($receiptItems->isEmpty()) {
            return;
        }

        $nextLine = (int) ($invoice->items()->max('line') ?? 0) + 1;
        $built = $this->buildLines($items, $receiptItems, (string) $invoice->tax_rate, (bool) $invoice->prices_include_tax);

        foreach ($built['lines'] as $line) {
            $line['line'] = $nextLine++;
            $invoice->items()->create($line);
        }

        $addedTotal = $built['totals']['total'];

        $invoice->update([
            'taxable_base' => Decimal::add((string) $invoice->taxable_base, $built['totals']['taxable_base'], 2),
            'exempt_base' => Decimal::add((string) $invoice->exempt_base, $built['totals']['exempt_base'], 2),
            'unaffected_base' => Decimal::add((string) $invoice->unaffected_base, $built['totals']['unaffected_base'], 2),
            'discount_total' => Decimal::add((string) $invoice->discount_total, $built['totals']['discount_total'], 2),
            'tax_total' => Decimal::add((string) $invoice->tax_total, $built['totals']['tax_total'], 2),
            'total' => Decimal::add((string) $invoice->total, $addedTotal, 2),
            'balance' => Decimal::add((string) $invoice->balance, $addedTotal, 2),
        ]);

        $installment->update([
            'amount' => Decimal::add((string) $installment->amount, $addedTotal, 2),
            'balance' => Decimal::add((string) $installment->balance, $addedTotal, 2),
        ]);

        $invoice->receipts()->attach($receipt->id);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PurchasesRuleException
     */
    private function resolveProvider(array $data, SupplierInvoiceKind $kind): ?Provider
    {
        if (empty($data['provider_id'])) {
            if ($kind === SupplierInvoiceKind::Purchase) {
                throw new PurchasesRuleException('El proveedor es obligatorio en facturas de compra', 'provider_id');
            }

            if (trim((string) ($data['counterparty_name'] ?? '')) === '') {
                throw new PurchasesRuleException('Indica el proveedor o el nombre del beneficiario', 'counterparty_name');
            }

            return null;
        }

        /** @var Provider|null $provider */
        $provider = Provider::query()->find($data['provider_id']);

        if ($provider === null) {
            throw new PurchasesRuleException('El proveedor no existe', 'provider_id');
        }

        return $provider;
    }

    /**
     * Sin proveedor (gasto con nombre libre) el documento siempre es "otro".
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveDocumentType(array $data, ?Provider $provider): SupplierDocumentType
    {
        return $provider === null
            ? SupplierDocumentType::Other
            : SupplierDocumentType::from((string) $data['document_type']);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PurchasesRuleException
     */
    private function resolveConcept(array $data, SupplierInvoiceKind $kind): ?FinanceConcept
    {
        if (empty($data['concept_id'])) {
            if ($kind === SupplierInvoiceKind::Expense) {
                throw new PurchasesRuleException('El concepto de egreso es obligatorio en gastos', 'concept_id');
            }

            return null;
        }

        /** @var FinanceConcept|null $concept */
        $concept = FinanceConcept::query()->find($data['concept_id']);

        if ($concept === null || ! $concept->is_active || $concept->is_system || $concept->direction !== ConceptDirection::Expense) {
            throw new PurchasesRuleException('El concepto debe ser un concepto de egreso activo y no de sistema', 'concept_id');
        }

        if ($kind === SupplierInvoiceKind::Expense) {
            $account = $concept->financialAccount;

            if ($account === null && $this->parameters->boolean('finance.require_concept_account')) {
                throw new PurchasesRuleException('El concepto no tiene cuenta contable asignada.', 'concept_id');
            }

            if ($account !== null && $account->type !== FinancialAccountType::Expense) {
                throw new PurchasesRuleException('El concepto debe estar imputado a una cuenta de gasto.', 'concept_id');
            }
        }

        return $concept;
    }

    /**
     * @throws PurchasesRuleException
     */
    private function assertDocumentIsUnique(?Provider $provider, SupplierDocumentType $type, mixed $series, string $number): void
    {
        if ($provider === null) {
            return;
        }

        $series = $series !== null ? (string) $series : '';

        $exists = SupplierInvoice::query()
            ->where('provider_id', $provider->id)
            ->where('document_type', $type->value)
            ->whereRaw("COALESCE(series, '') = ?", [$series])
            ->where('document_number', $number)
            ->where('status', '<>', SupplierInvoiceStatus::Cancelled->value)
            ->exists();

        if ($exists) {
            throw new PurchasesRuleException('El documento del proveedor ya está registrado en otra factura vigente', 'document_number');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PurchasesRuleException
     */
    private function resolveOrder(array $data, SupplierInvoiceKind $kind, ?Provider $provider, Branch $branch): ?PurchaseOrder
    {
        if (empty($data['purchase_order_id'])) {
            return null;
        }

        if ($kind === SupplierInvoiceKind::Expense) {
            throw new PurchasesRuleException('Un gasto directo no se vincula a órdenes de compra', 'purchase_order_id');
        }

        /** @var PurchaseOrder|null $order */
        $order = PurchaseOrder::query()->find($data['purchase_order_id']);

        if ($order === null || $order->branch_id !== $branch->id || $order->provider_id !== $provider?->id) {
            throw new PurchasesRuleException('La orden de compra no existe, no es del proveedor o no pertenece a la sede activa', 'purchase_order_id');
        }

        if (! in_array($order->status, self::INVOICEABLE_ORDER_STATUSES, true)) {
            throw new PurchasesRuleException('La orden de compra debe estar aprobada para facturarse', 'purchase_order_id');
        }

        return $order;
    }

    /**
     * Bloquea las recepciones en orden determinista y valida que puedan facturarse.
     *
     * @return Collection<int, PurchaseReceipt>
     *
     * @throws PurchasesRuleException
     */
    private function lockReceipts(mixed $receiptIds, SupplierInvoiceKind $kind, ?Provider $provider, Branch $branch, ?PurchaseOrder $order, Currency $currency): Collection
    {
        $ids = array_values(array_unique(array_map('strval', (array) $receiptIds)));

        if ($ids === []) {
            return new Collection;
        }

        if ($kind === SupplierInvoiceKind::Expense) {
            throw new PurchasesRuleException('Un gasto directo no se vincula a recepciones', 'receipt_ids');
        }

        $receipts = PurchaseReceipt::query()
            ->with('order:id,currency_id')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($receipts->count() !== count($ids)) {
            throw new PurchasesRuleException('Alguna de las recepciones no existe', 'receipt_ids');
        }

        foreach ($receipts as $receipt) {
            if ($receipt->provider_id !== $provider?->id || $receipt->branch_id !== $branch->id) {
                throw new PurchasesRuleException("La recepción {$receipt->number} no es del proveedor o no pertenece a la sede activa", 'receipt_ids');
            }

            if ($order !== null && $receipt->purchase_order_id !== $order->id) {
                throw new PurchasesRuleException("La recepción {$receipt->number} no corresponde a la orden de compra indicada", 'receipt_ids');
            }

            if ($receipt->order->currency_id !== $currency->id) {
                throw new PurchasesRuleException("La recepción {$receipt->number} es de una orden en otra moneda", 'currency_id');
            }
        }

        $this->assertReceiptsInvoiceable($receipts);

        return $receipts;
    }

    /**
     * @param  Collection<int, PurchaseReceipt>  $receipts
     *
     * @throws PurchasesRuleException
     */
    private function assertReceiptsInvoiceable(Collection $receipts): void
    {
        foreach ($receipts as $receipt) {
            if ($receipt->status !== PurchaseReceiptStatus::Confirmed) {
                throw new PurchasesRuleException("La recepción {$receipt->number} no está confirmada", 'receipt_ids');
            }

            if (self::isReceiptInvoiced($receipt->id)) {
                throw new PurchasesRuleException("La recepción {$receipt->number} ya está vinculada a otra factura", 'receipt_ids');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  Collection<string, PurchaseReceiptItem>  $receiptItems
     * @return array{lines: list<array<string, mixed>>, totals: array<string, string>}
     *
     * @throws PurchasesRuleException
     */
    private function buildLines(array $items, Collection $receiptItems, string $taxRate, bool $pricesIncludeTax): array
    {
        $lines = [];
        $inputs = [];

        foreach (array_values($items) as $index => $item) {
            $presentationId = $item['item_presentation_id'] ?? null;
            $receiptItemId = $item['purchase_receipt_item_id'] ?? null;
            $catalogItemId = null;
            $defaultTax = TaxType::Taxable;

            $receiptItem = null;
            if ($receiptItemId !== null) {
                $receiptItem = $receiptItems->get($receiptItemId);
                if ($receiptItem === null) {
                    throw new PurchasesRuleException('La línea de recepción no pertenece a las recepciones vinculadas', "items.{$index}.purchase_receipt_item_id");
                }

                $presentationId ??= $receiptItem->item_presentation_id;
                $catalogItemId = $receiptItem->catalog_item_id;
                $defaultTax = $receiptItem->orderItem->tax_type;
            }

            if ($presentationId !== null) {
                $presentation = ItemPresentation::query()->with('item')->whereKey($presentationId)->first();
                if ($presentation === null || $presentation->item === null) {
                    throw new PurchasesRuleException('La presentación del artículo no existe', "items.{$index}.item_presentation_id");
                }

                if ($catalogItemId !== null && $catalogItemId !== $presentation->item->id) {
                    throw new PurchasesRuleException('El artículo no coincide con la línea de recepción', "items.{$index}.item_presentation_id");
                }

                $catalogItemId = $presentation->item->id;
                if ($receiptItem === null) {
                    $defaultTax = $presentation->item->tax_type;
                }
            }

            $taxType = isset($item['tax_type']) ? TaxType::from((string) $item['tax_type']) : $defaultTax;
            $discount = (string) ($item['discount_percent'] ?? '0');

            $inputs[] = new LineInput(
                quantity: (string) $item['quantity'],
                unitPrice: (string) $item['unit_price'],
                discountPercent: $discount,
                taxType: $taxType,
            );

            $lines[] = [
                'line' => $index + 1,
                'description' => (string) $item['description'],
                'catalog_item_id' => $catalogItemId,
                'item_presentation_id' => $presentationId,
                'purchase_receipt_item_id' => $receiptItem?->id,
                'quantity' => (string) $item['quantity'],
                'unit_price' => (string) $item['unit_price'],
                'discount_percent' => $discount,
                'tax_type' => $taxType,
            ];
        }

        $result = $this->calculator->calculate($inputs, $taxRate, $pricesIncludeTax);

        foreach ($result->lines as $i => $line) {
            $lines[$i]['line_subtotal'] = $line->subtotal;
            $lines[$i]['line_tax'] = $line->tax;
            $lines[$i]['line_total'] = $line->total;
        }

        if (Decimal::cmp($result->total, '0', 2) <= 0) {
            throw new PurchasesRuleException('El total de la factura debe ser mayor a cero', 'items');
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
     * Sin cronograma se crea una cuota única al vencimiento; con cronograma la suma debe igualar el total.
     *
     * @return list<array{number: int, due_date: string, amount: string}>
     *
     * @throws PurchasesRuleException
     */
    private function buildInstallments(mixed $given, string $total, Carbon $issueDate, Carbon $dueDate): array
    {
        if (! is_array($given) || $given === []) {
            return [['number' => 1, 'due_date' => $dueDate->toDateString(), 'amount' => $total]];
        }

        usort($given, fn (array $a, array $b) => strcmp((string) $a['due_date'], (string) $b['due_date']));

        $sum = '0';
        $installments = [];
        foreach ($given as $index => $row) {
            $date = Carbon::parse((string) $row['due_date'])->startOfDay();
            if ($date->lt($issueDate)) {
                throw new PurchasesRuleException('El vencimiento de una cuota no puede ser anterior a la emisión', "installments.{$index}.due_date");
            }

            $amount = number_format((float) $row['amount'], 2, '.', '');
            $sum = Decimal::add($sum, $amount, 2);
            $installments[] = ['number' => $index + 1, 'due_date' => $date->toDateString(), 'amount' => $amount];
        }

        if (Decimal::cmp($sum, $total, 2) !== 0) {
            throw new PurchasesRuleException("La suma de las cuotas ({$sum}) debe ser igual al total de la factura ({$total})", 'installments');
        }

        return $installments;
    }

    /**
     * Pago mixto: la primera cuota es el monto al contado (vencimiento = emisión, se paga en el acto) y el resto
     * del cronograma (`$given`, o la cuota única por defecto si no se personaliza) se calcula sobre el saldo
     * restante, no sobre el total completo. Sin monto al contado, o si es exactamente el total, el comportamiento
     * es idéntico al de `buildInstallments()` (pago completo o financiamiento total, sin cambios).
     *
     * @return list<array{number: int, due_date: string, amount: string}>
     *
     * @throws PurchasesRuleException
     */
    private function buildInstallmentsWithDownPayment(mixed $given, ?string $downPayment, string $total, Carbon $issueDate, Carbon $dueDate): array
    {
        if ($downPayment === null || Decimal::cmp($downPayment, $total, 2) === 0) {
            return $this->buildInstallments($given, $total, $issueDate, $dueDate);
        }

        if (Decimal::cmp($downPayment, '0', 2) <= 0 || Decimal::cmp($downPayment, $total, 2) > 0) {
            throw new PurchasesRuleException('El monto al contado debe ser mayor a cero y no superar el total de la factura', 'down_payment_amount');
        }

        $remainder = Decimal::sub($total, $downPayment, 2);
        $remainderRows = $this->buildInstallments($given, $remainder, $issueDate, $dueDate);

        $rows = [['number' => 1, 'due_date' => $issueDate->toDateString(), 'amount' => $downPayment]];
        foreach ($remainderRows as $row) {
            $rows[] = ['number' => count($rows) + 1, 'due_date' => $row['due_date'], 'amount' => $row['amount']];
        }

        return $rows;
    }

    /**
     * Advierte (sin bloquear) cuando el total difiere del valor de lo recibido más allá de la tolerancia.
     *
     * @param  Collection<string, PurchaseReceiptItem>  $receiptItems
     * @return list<string>
     */
    private function matchWarnings(string $total, Collection $receiptItems, ?PurchaseOrder $order, string $taxRate): array
    {
        $inputs = [];
        foreach ($receiptItems as $item) {
            $inputs[] = new LineInput(
                quantity: (string) $item->quantity,
                unitPrice: (string) $item->orderItem->unit_price,
                discountPercent: (string) $item->orderItem->discount_percent,
                taxType: $item->orderItem->tax_type,
            );
        }

        if ($inputs === []) {
            return [];
        }

        $includeTax = $order !== null ? $order->prices_include_tax : true;
        $rate = $order !== null ? (string) $order->tax_rate : $taxRate;
        $expected = $this->calculator->calculate($inputs, $rate, $includeTax)->total;

        if (Decimal::cmp($expected, '0', 2) <= 0) {
            return [];
        }

        $difference = Decimal::sub($total, $expected, 2);
        $absolute = str_starts_with($difference, '-') ? substr($difference, 1) : $difference;
        $percent = Decimal::div(Decimal::mul($absolute, '100', 6), $expected, 2);
        $tolerance = number_format((float) ($this->parameters->get('purchases.invoice_match_tolerance_percent') ?? 1.0), 2, '.', '');

        if (Decimal::cmp($percent, $tolerance, 2) <= 0) {
            return [];
        }

        return ["El total de la factura ({$total}) difiere en {$percent}% del valor de lo recibido ({$expected}); la tolerancia es {$tolerance}%."];
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

        // Las deudas en divisa se registran al tipo de cambio de venta, igual que las órdenes de compra.
        $current = $this->exchangeRates->current($currency->id, $date);
        if ($current['rate'] === null) {
            throw new PurchasesRuleException('Indica el tipo de cambio: no hay uno registrado para la moneda', 'exchange_rate');
        }

        return number_format((float) $current['rate']->sell_rate, 4, '.', '');
    }

    private function receiptItemDescription(PurchaseReceiptItem $item): string
    {
        $name = $item->catalogItem->name ?? $item->orderItem->description ?? 'Servicio';
        $presentation = $item->presentation?->name;

        return $presentation !== null && $presentation !== '' ? "{$name} ({$presentation})" : $name;
    }
}
