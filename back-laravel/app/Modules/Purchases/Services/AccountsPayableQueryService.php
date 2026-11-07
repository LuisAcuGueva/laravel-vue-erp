<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Finance\Models\Currency;
use App\Modules\Purchases\Enums\SupplierInvoiceStatus;
use App\Modules\Purchases\Enums\SupplierPaymentStatus;
use App\Modules\Purchases\Models\PayableInstallment;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\SupplierInvoice;
use App\Modules\Purchases\Models\SupplierPayment;
use App\Support\Pricing\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de cuentas por pagar: cuotas con saldo, antigüedad de deuda y estado de cuenta.
 * Ningún resultado suma montos de monedas distintas.
 */
class AccountsPayableQueryService
{
    private const OPEN_STATUSES = [
        SupplierInvoiceStatus::Pending->value,
        SupplierInvoiceStatus::PartiallyPaid->value,
    ];

    /**
     * Cuotas con saldo de la sede, ordenadas por vencimiento.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<PayableInstallment>
     */
    public function openInstallments(array $filters, string $branchId): Builder
    {
        $query = PayableInstallment::query()
            ->with([
                'invoice:id,number,kind,document_type,series,document_number,issue_date,counterparty_name,branch_id',
                'provider:id,document_type,document_number,business_name',
                'currency:id,code,symbol',
            ])
            ->whereHas('invoice', fn (Builder $q) => $q->where('branch_id', $branchId));

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        } else {
            $query->whereIn('status', self::OPEN_STATUSES);
        }

        foreach (['provider_id', 'currency_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, (string) $filters[$field]);
            }
        }

        if (! empty($filters['overdue'])) {
            $query->whereDate('due_date', '<', today()->toDateString())->where('balance', '>', 0);
        }

        if (! empty($filters['due_from'])) {
            $query->whereDate('due_date', '>=', (string) $filters['due_from']);
        }

        if (! empty($filters['due_to'])) {
            $query->whereDate('due_date', '<=', (string) $filters['due_to']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $inner) use ($like, $operator) {
                $inner->whereHas('invoice', fn (Builder $i) => $i->where('number', $operator, $like)
                    ->orWhere('document_number', $operator, $like)
                    ->orWhere('counterparty_name', $operator, $like))
                    ->orWhereHas('provider', fn (Builder $p) => $p->withTrashed()
                        ->where('business_name', $operator, $like)
                        ->orWhere('document_number', $operator, $like));
            });
        }

        return $query->orderBy('due_date')->orderBy('supplier_invoice_id')->orderBy('number');
    }

    /**
     * Saldo total de un listado de cuotas, separado por moneda.
     *
     * @param  Builder<PayableInstallment>  $query
     * @return list<array{currency_id: string, code: string, symbol: string, balance: string, overdue: string}>
     */
    public function totalsByCurrency(Builder $query): array
    {
        $today = today()->toDateString();

        $rows = (clone $query)
            ->reorder()
            ->setEagerLoads([])
            ->select('currency_id')
            ->selectRaw('SUM(balance) AS balance')
            ->selectRaw('SUM(CASE WHEN due_date < ? THEN balance ELSE 0 END) AS overdue', [$today])
            ->groupBy('currency_id')
            ->toBase()
            ->get();

        $currencies = Currency::query()->whereIn('id', $rows->pluck('currency_id'))->get()->keyBy('id');

        return array_values($rows->map(fn (object $row) => [
            'currency_id' => (string) $row->currency_id,
            'code' => (string) $currencies->get($row->currency_id)?->code,
            'symbol' => (string) $currencies->get($row->currency_id)?->symbol,
            'balance' => number_format((float) $row->balance, 2, '.', ''),
            'overdue' => number_format((float) $row->overdue, 2, '.', ''),
        ])->all());
    }

    /**
     * Antigüedad de saldos por proveedor y moneda a una fecha de corte.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function aging(string $branchId, ?string $asOf = null, array $filters = []): array
    {
        $cut = Carbon::parse($asOf ?? today()->toDateString())->toDateString();
        $days = 'CAST(? AS DATE) - pi.due_date';

        $query = DB::table('payable_installments AS pi')
            ->join('supplier_invoices AS si', 'si.id', '=', 'pi.supplier_invoice_id')
            ->where('si.branch_id', $branchId)
            ->whereIn('pi.status', self::OPEN_STATUSES)
            ->where('pi.balance', '>', 0)
            ->select('pi.provider_id', 'pi.currency_id')
            ->selectRaw('SUM(CASE WHEN pi.due_date >= CAST(? AS DATE) THEN pi.balance ELSE 0 END) AS current_amount', [$cut])
            ->selectRaw("SUM(CASE WHEN {$days} BETWEEN 1 AND 30 THEN pi.balance ELSE 0 END) AS d1_30", [$cut])
            ->selectRaw("SUM(CASE WHEN {$days} BETWEEN 31 AND 60 THEN pi.balance ELSE 0 END) AS d31_60", [$cut])
            ->selectRaw("SUM(CASE WHEN {$days} BETWEEN 61 AND 90 THEN pi.balance ELSE 0 END) AS d61_90", [$cut])
            ->selectRaw("SUM(CASE WHEN {$days} > 90 THEN pi.balance ELSE 0 END) AS over_90", [$cut])
            ->selectRaw('SUM(pi.balance) AS total')
            ->groupBy('pi.provider_id', 'pi.currency_id');

        foreach (['provider_id', 'currency_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where("pi.{$field}", (string) $filters[$field]);
            }
        }

        $rows = $query->get();

        $providers = Provider::withTrashed()->whereIn('id', $rows->pluck('provider_id')->filter())->get(['id', 'document_type', 'document_number', 'business_name'])->keyBy('id');
        $currencies = Currency::query()->whereIn('id', $rows->pluck('currency_id'))->get(['id', 'code', 'symbol'])->keyBy('id');
        $money = static fn (mixed $v): string => number_format((float) $v, 2, '.', '');

        return array_values($rows->map(function (object $row) use ($providers, $currencies, $money): array {
            $provider = $row->provider_id !== null ? $providers->get($row->provider_id) : null;
            $currency = $currencies->get($row->currency_id);

            return [
                'provider' => $provider !== null
                    ? ['id' => $provider->id, 'document_type' => $provider->document_type->value, 'document_number' => $provider->document_number, 'business_name' => $provider->business_name]
                    : ['id' => null, 'document_type' => null, 'document_number' => null, 'business_name' => 'Gastos sin proveedor'],
                'currency' => ['id' => $row->currency_id, 'code' => $currency?->code, 'symbol' => $currency?->symbol],
                'current' => $money($row->current_amount),
                'd1_30' => $money($row->d1_30),
                'd31_60' => $money($row->d31_60),
                'd61_90' => $money($row->d61_90),
                'over_90' => $money($row->over_90),
                'total' => $money($row->total),
            ];
        })
            ->sortBy([['currency.code', 'asc'], ['total', 'desc']])
            ->all());
    }

    /**
     * Estado de cuenta del proveedor en una moneda: facturas como cargo y pagos como abono.
     *
     * @return array{provider: array<string, mixed>, currency: array<string, mixed>, date_from: string, date_to: string, opening: string, entries: list<array<string, mixed>>, closing: string}
     */
    public function statement(Provider $provider, string $branchId, string $dateFrom, string $dateTo, ?string $currencyId = null): array
    {
        $currency = $currencyId !== null
            ? Currency::query()->findOrFail($currencyId)
            : (Currency::base() ?? Currency::query()->where('is_base', true)->firstOrFail());

        $invoices = SupplierInvoice::query()
            ->where('provider_id', $provider->id)
            ->where('branch_id', $branchId)
            ->where('currency_id', $currency->id)
            ->where('status', '<>', SupplierInvoiceStatus::Cancelled->value);

        $payments = SupplierPayment::query()
            ->where('provider_id', $provider->id)
            ->where('branch_id', $branchId)
            ->where('currency_id', $currency->id)
            ->where('status', SupplierPaymentStatus::Completed->value);

        $charged = (string) (clone $invoices)->whereDate('issue_date', '<', $dateFrom)->sum('total');
        $paid = (string) (clone $payments)->whereDate('payment_date', '<', $dateFrom)->sum('amount');
        $opening = Decimal::sub(number_format((float) $charged, 2, '.', ''), number_format((float) $paid, 2, '.', ''), 2);

        $entries = [];

        foreach ((clone $invoices)->whereBetween('issue_date', [$dateFrom, $dateTo])->get() as $invoice) {
            $entries[] = [
                'date' => $invoice->issue_date->format('Y-m-d'),
                'type' => 'invoice',
                'id' => $invoice->id,
                'number' => $invoice->number,
                'description' => "{$invoice->document_type->label()} {$invoice->document_label}",
                'charge' => (string) $invoice->total,
                'credit' => '0.00',
                'sort' => $invoice->created_at->getTimestamp(),
            ];
        }

        foreach ((clone $payments)->with('paymentMethod:id,name')->whereBetween('payment_date', [$dateFrom, $dateTo])->get() as $payment) {
            $entries[] = [
                'date' => $payment->payment_date->format('Y-m-d'),
                'type' => 'payment',
                'id' => $payment->id,
                'number' => $payment->number,
                'description' => trim("Pago {$payment->paymentMethod->name} ".($payment->reference ?? '')),
                'charge' => '0.00',
                'credit' => (string) $payment->amount,
                'sort' => $payment->created_at->getTimestamp(),
            ];
        }

        usort($entries, fn (array $a, array $b) => [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']]);

        $balance = $opening;
        foreach ($entries as $i => $entry) {
            $balance = Decimal::sub(Decimal::add($balance, $entry['charge'], 2), $entry['credit'], 2);
            unset($entry['sort']);
            $entry['balance'] = $balance;
            $entries[$i] = $entry;
        }

        return [
            'provider' => ['id' => $provider->id, 'document_number' => $provider->document_number, 'business_name' => $provider->business_name],
            'currency' => ['id' => $currency->id, 'code' => $currency->code, 'symbol' => $currency->symbol],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'opening' => $opening,
            'entries' => $entries,
            'closing' => $balance,
        ];
    }
}
