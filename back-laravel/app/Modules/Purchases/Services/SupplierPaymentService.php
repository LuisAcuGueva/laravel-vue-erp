<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Finance\Data\SettlementData;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Services\ExchangeRateService;
use App\Modules\Finance\Services\MoneySettlementService;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\SupplierInvoiceStatus;
use App\Modules\Purchases\Enums\SupplierPaymentStatus;
use App\Modules\Purchases\Exceptions\PurchasesRuleException;
use App\Modules\Purchases\Models\PayableInstallment;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Models\SupplierPayment;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use App\Support\Pricing\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public function __construct(
        private readonly MoneySettlementService $settlement,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<SupplierPayment>
     */
    public function query(array $filters, string $branchId): Builder
    {
        $query = SupplierPayment::query()
            ->with([
                'provider:id,document_type,document_number,business_name',
                'currency:id,code,symbol',
                'paymentMethod:id,code,name,settlement',
                'allocations.invoice:id,number,series,document_number,document_type',
            ])
            ->withCount('allocations')
            ->where('branch_id', $branchId);

        foreach (['status', 'provider_id', 'currency_id', 'payment_method_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, (string) $filters[$field]);
            }
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $inner) use ($like, $operator) {
                $inner->where('number', $operator, $like)
                    ->orWhere('reference', $operator, $like)
                    ->orWhere('counterparty_name', $operator, $like)
                    ->orWhereHas('provider', fn ($p) => $p->withTrashed()
                        ->where('business_name', $operator, $like)
                        ->orWhere('document_number', $operator, $like));
            });
        }

        return $query->orderByDesc('payment_date')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function findById(string $id): ?SupplierPayment
    {
        return SupplierPayment::query()
            ->with([
                'provider:id,document_type,document_number,business_name',
                'currency:id,code,symbol',
                'paymentMethod:id,code,name,settlement',
                'cashSession:id,number,box_id',
                'bankAccount:id,alias,account_number',
                'creator:id,name',
                'canceller:id,name',
                'allocations.installment:id,number,due_date,amount,balance',
                'allocations.invoice:id,number,series,document_number,document_type',
            ])
            ->find($id);
    }

    /**
     * Registra un pago aplicado a una o varias cuotas y lo liquida en caja o banco.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws PurchasesRuleException
     */
    public function create(array $data, User $user, Branch $branch): SupplierPayment
    {
        return DB::transaction(function () use ($data, $user, $branch): SupplierPayment {
            /** @var list<array{installment_id: string, amount: string}> $allocations */
            $allocations = array_values((array) $data['allocations']);

            if ($allocations === []) {
                throw new PurchasesRuleException('Indica al menos una cuota a pagar', 'allocations');
            }

            $installments = $this->lockInstallments(array_column($allocations, 'installment_id'));
            $providerId = $data['provider_id'] ?? null;
            $currencyId = (string) $data['currency_id'];

            $amount = '0';
            $rows = [];
            foreach ($allocations as $index => $allocation) {
                /** @var PayableInstallment $installment */
                $installment = $installments->get($allocation['installment_id']);
                $value = number_format((float) $allocation['amount'], 2, '.', '');

                $this->assertAllocatable($installment, $providerId, $currencyId, $branch, $value, $index);

                $amount = Decimal::add($amount, $value, 2);
                $rows[] = ['installment' => $installment, 'amount' => $value];
            }

            $invoiceIds = array_values(array_unique(array_map('strval', $installments->pluck('supplier_invoice_id')->all())));
            $counterpartyName = $this->resolveCounterparty($providerId, $invoiceIds);

            /** @var PaymentMethod $method */
            $method = PaymentMethod::query()->findOrFail($data['payment_method_id']);
            /** @var Currency $currency */
            $currency = Currency::query()->findOrFail($currencyId);
            $paymentDate = Carbon::parse((string) ($data['payment_date'] ?? today()->toDateString()))->startOfDay();

            $payment = SupplierPayment::create([
                'number' => SequentialCodeGenerator::next('supplier_payment', 'PP'),
                'branch_id' => $branch->id,
                'provider_id' => $providerId,
                'counterparty_name' => $providerId === null ? $counterpartyName : null,
                'payment_date' => $paymentDate->toDateString(),
                'currency_id' => $currency->id,
                'exchange_rate' => $this->resolveExchangeRate($currency, $paymentDate->toDateString(), $rows[0]['installment']->invoice),
                'payment_method_id' => $method->id,
                'cash_session_id' => $method->settlement === Settlement::CashBox ? ($data['cash_session_id'] ?? null) : null,
                'bank_account_id' => $method->settlement === Settlement::BankAccount ? $method->bank_account_id : null,
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => SupplierPaymentStatus::Completed,
                'created_by' => $user->id,
            ]);

            foreach ($rows as $row) {
                $installment = $row['installment'];
                $payment->allocations()->create([
                    'payable_installment_id' => $installment->id,
                    'supplier_invoice_id' => $installment->supplier_invoice_id,
                    'amount' => $row['amount'],
                ]);

                $paid = Decimal::add((string) $installment->paid_amount, $row['amount'], 2);
                $installment->update([
                    'paid_amount' => $paid,
                    'balance' => Decimal::sub((string) $installment->amount, $paid, 2),
                    'status' => SupplierInvoiceStatus::fromAmounts((string) $installment->amount, $paid),
                ]);
            }

            $this->refreshInvoices($invoiceIds);

            $this->settlement->settle(new SettlementData(
                paymentMethodId: $method->id,
                direction: MoneyDirection::Out,
                amount: $amount,
                currencyId: $currency->id,
                source: $payment,
                sourceNumber: $payment->number,
                branchId: $branch->id,
                cashSessionId: $payment->cash_session_id,
                concept: SystemConcept::SupplierPayment,
                reference: $payment->reference,
                description: "Pago {$payment->number} a {$counterpartyName}",
                counterpartyName: $counterpartyName,
                movementDate: $paymentDate,
                user: $user,
            ));

            return $this->findById($payment->id) ?? $payment;
        });
    }

    /**
     * Anula el pago: revierte su movimiento de caja o banco y restaura los saldos de cuotas y facturas.
     *
     * @throws PurchasesRuleException
     */
    public function cancel(SupplierPayment $payment, string $reason, User $user): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $reason, $user): SupplierPayment {
            /** @var SupplierPayment $locked */
            $locked = SupplierPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === SupplierPaymentStatus::Cancelled) {
                throw new PurchasesRuleException('El pago ya se encuentra anulado', 'status');
            }

            $this->settlement->unsettle($locked, $locked->number, $reason, $user->id);

            $allocations = $locked->allocations()->get();
            $installments = $this->lockInstallments($allocations->pluck('payable_installment_id')->all());

            foreach ($allocations as $allocation) {
                /** @var PayableInstallment $installment */
                $installment = $installments->get($allocation->payable_installment_id);

                $paid = Decimal::sub((string) $installment->paid_amount, (string) $allocation->amount, 2);
                $installment->update([
                    'paid_amount' => $paid,
                    'balance' => Decimal::sub((string) $installment->amount, $paid, 2),
                    'status' => SupplierInvoiceStatus::fromAmounts((string) $installment->amount, $paid),
                ]);
            }

            $this->refreshInvoices($allocations->pluck('supplier_invoice_id')->unique()->values()->all());

            $locked->update([
                'status' => SupplierPaymentStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $this->findById($locked->id) ?? $locked;
        });
    }

    /**
     * Bloquea las cuotas en orden determinista (por id) para evitar interbloqueos entre pagos concurrentes.
     *
     * @param  array<int, mixed>  $ids
     * @return Collection<string, PayableInstallment>
     *
     * @throws PurchasesRuleException
     */
    private function lockInstallments(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('strval', $ids)));

        $installments = PayableInstallment::query()
            ->with('invoice:id,branch_id,number,counterparty_name,exchange_rate,currency_id,status')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($installments->count() !== count($ids)) {
            throw new PurchasesRuleException('Alguna de las cuotas no existe', 'allocations');
        }

        return $installments;
    }

    /**
     * @throws PurchasesRuleException
     */
    private function assertAllocatable(PayableInstallment $installment, ?string $providerId, string $currencyId, Branch $branch, string $amount, int $index): void
    {
        $field = "allocations.{$index}.amount";

        if ($installment->status === SupplierInvoiceStatus::Cancelled || $installment->status === SupplierInvoiceStatus::Paid) {
            throw new PurchasesRuleException("La cuota {$installment->number} de la factura {$installment->invoice->number} no tiene saldo pendiente", $field);
        }

        if ($installment->provider_id !== $providerId) {
            throw new PurchasesRuleException("La cuota {$installment->number} de la factura {$installment->invoice->number} no es del proveedor del pago", "allocations.{$index}.installment_id");
        }

        if ($installment->currency_id !== $currencyId) {
            throw new PurchasesRuleException("La cuota {$installment->number} de la factura {$installment->invoice->number} está en otra moneda", "allocations.{$index}.installment_id");
        }

        if ($installment->invoice->branch_id !== $branch->id) {
            throw new PurchasesRuleException("La factura {$installment->invoice->number} no pertenece a la sede activa", "allocations.{$index}.installment_id");
        }

        if (Decimal::cmp($amount, '0', 2) <= 0) {
            throw new PurchasesRuleException('El monto a pagar debe ser mayor a cero', $field);
        }

        if (Decimal::cmp($amount, (string) $installment->balance, 2) > 0) {
            throw new PurchasesRuleException("El monto supera el saldo de la cuota ({$installment->balance})", $field);
        }
    }

    /**
     * Sin proveedor (gasto con beneficiario libre) todas las cuotas deben ser de la misma factura.
     *
     * @param  list<string>  $invoiceIds
     *
     * @throws PurchasesRuleException
     */
    private function resolveCounterparty(?string $providerId, array $invoiceIds): string
    {
        if ($providerId !== null) {
            /** @var Provider $provider */
            $provider = Provider::withTrashed()->findOrFail($providerId);

            return $provider->business_name;
        }

        if (count($invoiceIds) !== 1) {
            throw new PurchasesRuleException('Un pago sin proveedor solo puede aplicarse a una factura', 'allocations');
        }

        /** @var SupplierInvoice $invoice */
        $invoice = SupplierInvoice::query()->findOrFail($invoiceIds[0]);

        return (string) $invoice->counterparty_name;
    }

    /**
     * Recalcula lo pagado, el saldo y el estado de cada factura a partir de sus cuotas.
     *
     * @param  array<int, mixed>  $invoiceIds
     */
    private function refreshInvoices(array $invoiceIds): void
    {
        $invoices = SupplierInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($invoices as $invoice) {
            $paid = number_format((float) PayableInstallment::query()->where('supplier_invoice_id', $invoice->id)->sum('paid_amount'), 2, '.', '');

            $invoice->update([
                'paid_amount' => $paid,
                'balance' => Decimal::sub((string) $invoice->total, $paid, 2),
                'status' => SupplierInvoiceStatus::fromAmounts((string) $invoice->total, $paid),
            ]);
        }
    }

    private function resolveExchangeRate(Currency $currency, string $date, SupplierInvoice $invoice): string
    {
        $base = Currency::base();

        if ($base === null || $currency->id === $base->id) {
            return '1.0000';
        }

        $current = $this->exchangeRates->current($currency->id, $date);

        return $current['rate'] !== null
            ? number_format((float) $current['rate']->sell_rate, 4, '.', '')
            : number_format((float) $invoice->exchange_rate, 4, '.', '');
    }
}
