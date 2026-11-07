<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Models\Currency;
use App\Modules\Sales\Enums\CollectionStatus;
use App\Modules\Sales\Enums\CreditNoteStatus;
use App\Modules\Sales\Enums\InstallmentStatus;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\Collection;
use App\Modules\Sales\Models\CollectionAllocation;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\ReceivableInstallment;
use App\Modules\Sales\Models\Sale;
use App\Support\Pricing\Decimal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ReceivableQueryService
{
    /**
     * Construye la consulta de cuotas por cobrar con filtros.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<ReceivableInstallment>
     */
    public function openInstallmentsQuery(array $filters = []): Builder
    {
        $query = ReceivableInstallment::query()
            ->with(['client', 'currency', 'sale.series']);

        if (! empty($filters['branch_id'])) {
            $query->whereHas('sale', fn (Builder $q) => $q->where('branch_id', $filters['branch_id']));
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['currency_id'])) {
            $query->where('currency_id', $filters['currency_id']);
        }

        $rawStatus = $filters['status'] ?? null;
        $statuses = is_array($rawStatus)
            ? $rawStatus
            : (is_string($rawStatus) && trim($rawStatus) !== '' ? explode(',', $rawStatus) : []);
        $statuses = array_values(array_filter(array_map('trim', $statuses)));

        $today = Carbon::today()->toDateString();

        if (in_array('overdue', $statuses, true)) {
            $query->where('status', '!=', InstallmentStatus::Cancelled)
                ->where('balance', '>', 0)
                ->where('due_date', '<', $today);
        } elseif (empty($statuses) || in_array('open', $statuses, true)) {
            // Por defecto cuotas pendientes o con saldo positivo no canceladas
            $query->where('status', '!=', InstallmentStatus::Cancelled)
                ->where('balance', '>', 0);
        } else {
            $enumStatuses = [];
            foreach ($statuses as $s) {
                $case = InstallmentStatus::tryFrom($s);
                if ($case !== null) {
                    $enumStatuses[] = $case;
                }
            }

            if (! empty($enumStatuses)) {
                $query->whereIn('status', $enumStatuses);

                // Si no se solicitan estados cerrados (paid, cancelled), exigir balance > 0
                $hasClosedStatus = in_array(InstallmentStatus::Paid, $enumStatuses, true)
                    || in_array(InstallmentStatus::Cancelled, $enumStatuses, true);
                if (! $hasClosedStatus) {
                    $query->where('balance', '>', 0);
                }
            } elseif (! in_array('all', $statuses, true)) {
                $query->where('status', '!=', InstallmentStatus::Cancelled)
                    ->where('balance', '>', 0);
            }
        }

        if (isset($filters['overdue']) && $filters['overdue'] !== '' && $filters['overdue'] !== null) {
            $isOverdue = filter_var($filters['overdue'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                ?? ($filters['overdue'] === '1' || $filters['overdue'] === 1);
            if ($isOverdue) {
                $query->where('due_date', '<', $today)->where('balance', '>', 0);
            } else {
                $query->where('due_date', '>=', $today);
            }
        }

        if (! empty($filters['due_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_from']);
        }

        if (! empty($filters['due_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_to']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->whereHas('sale', fn (Builder $sq) => $sq->where('full_number', 'ilike', "%{$search}%"))
                    ->orWhereHas('client', fn (Builder $cq) => $cq->where('name', 'ilike', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('due_date', 'asc')->orderBy('number', 'asc');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ReceivableInstallment>
     */
    public function openInstallments(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, ReceivableInstallment> $paginator */
        $paginator = $this->openInstallmentsQuery($filters)->paginate($perPage);

        return $paginator;
    }

    /**
     * Resumen de totales por moneda para las cuotas filtradas.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{currency_id: string, currency_code: string, currency_symbol: string, total_amount: string, total_paid: string, total_balance: string, overdue_balance: string}>
     */
    public function openInstallmentsSummary(array $filters = []): array
    {
        $baseQuery = ReceivableInstallment::query();

        if (! empty($filters['branch_id'])) {
            $baseQuery->whereHas('sale', fn (Builder $q) => $q->where('branch_id', $filters['branch_id']));
        }
        if (! empty($filters['client_id'])) {
            $baseQuery->where('client_id', $filters['client_id']);
        }
        if (! empty($filters['currency_id'])) {
            $baseQuery->where('currency_id', $filters['currency_id']);
        }
        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $baseQuery->where(function (Builder $q) use ($search): void {
                $q->whereHas('sale', fn (Builder $sq) => $sq->where('full_number', 'ilike', "%{$search}%"))
                    ->orWhereHas('client', fn (Builder $cq) => $cq->where('name', 'ilike', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%"));
            });
        }

        $baseQuery->where('status', '!=', InstallmentStatus::Cancelled)->where('balance', '>', 0);

        $today = Carbon::today()->toDateString();

        $rows = (clone $baseQuery)
            ->toBase()
            ->selectRaw('currency_id, SUM(amount) as sum_amount, SUM(paid_amount) as sum_paid, SUM(balance) as sum_balance, SUM(CASE WHEN due_date < ? THEN balance ELSE 0 END) as sum_overdue', [$today])
            ->groupBy('currency_id')
            ->get();

        $currencies = Currency::whereIn('id', $rows->pluck('currency_id'))->get()->keyBy('id');

        $result = [];
        foreach ($rows as $row) {
            $curr = $currencies->get((string) $row->currency_id);
            $result[] = [
                'currency_id' => (string) $row->currency_id,
                'currency_code' => $curr ? $curr->code : 'PEN',
                'currency_symbol' => $curr ? $curr->symbol : 'S/',
                'total_amount' => number_format((float) $row->sum_amount, 2, '.', ''),
                'total_paid' => number_format((float) $row->sum_paid, 2, '.', ''),
                'total_balance' => number_format((float) $row->sum_balance, 2, '.', ''),
                'overdue_balance' => number_format((float) $row->sum_overdue, 2, '.', ''),
            ];
        }

        return $result;
    }

    /**
     * Reporte de antigüedad de deuda (Aging) agrupado por cliente y moneda.
     *
     * @return array{as_of: string, items: array<int, array<string, mixed>>, totals: array<int, array<string, mixed>>}
     */
    public function aging(?string $branchId = null, ?string $asOf = null): array
    {
        $asOfDate = $asOf ? Carbon::parse($asOf) : Carbon::today();
        $asOfStr = $asOfDate->toDateString();

        $query = ReceivableInstallment::query()
            ->with(['client', 'currency', 'sale'])
            ->where('status', '!=', InstallmentStatus::Cancelled)
            ->where('balance', '>', 0);

        if (! empty($branchId)) {
            $query->whereHas('sale', fn (Builder $q) => $q->where('branch_id', $branchId));
        }

        $installments = $query->get();

        $grouped = [];
        $totalsByCurrency = [];

        foreach ($installments as $inst) {
            $key = "{$inst->client_id}_{$inst->currency_id}";

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'client_id' => $inst->client_id,
                    'client_name' => $inst->client->name,
                    'client_document_number' => $inst->client->document_number ?? '—',
                    'currency_id' => $inst->currency_id,
                    'currency_code' => $inst->currency->code,
                    'currency_symbol' => $inst->currency->symbol,
                    'current' => '0.00',
                    'days_1_30' => '0.00',
                    'days_31_60' => '0.00',
                    'days_61_90' => '0.00',
                    'days_over_90' => '0.00',
                    'total' => '0.00',
                ];
            }

            $currKey = $inst->currency_id;
            if (! isset($totalsByCurrency[$currKey])) {
                $totalsByCurrency[$currKey] = [
                    'currency_id' => $currKey,
                    'currency_code' => $inst->currency->code,
                    'currency_symbol' => $inst->currency->symbol,
                    'current' => '0.00',
                    'days_1_30' => '0.00',
                    'days_31_60' => '0.00',
                    'days_61_90' => '0.00',
                    'days_over_90' => '0.00',
                    'total' => '0.00',
                ];
            }

            $balance = (string) $inst->balance;
            $dueDate = $inst->due_date;

            $bucket = 'current';
            if ($dueDate->lt($asOfDate)) {
                $days = $dueDate->diffInDays($asOfDate);
                if ($days <= 30) {
                    $bucket = 'days_1_30';
                } elseif ($days <= 60) {
                    $bucket = 'days_31_60';
                } elseif ($days <= 90) {
                    $bucket = 'days_61_90';
                } else {
                    $bucket = 'days_over_90';
                }
            }

            $grouped[$key][$bucket] = Decimal::add((string) $grouped[$key][$bucket], $balance, 2);
            $grouped[$key]['total'] = Decimal::add((string) $grouped[$key]['total'], $balance, 2);

            $totalsByCurrency[$currKey][$bucket] = Decimal::add((string) $totalsByCurrency[$currKey][$bucket], $balance, 2);
            $totalsByCurrency[$currKey]['total'] = Decimal::add((string) $totalsByCurrency[$currKey]['total'], $balance, 2);
        }

        $items = array_values($grouped);
        usort($items, fn (array $a, array $b): int => Decimal::cmp((string) $b['total'], (string) $a['total'], 2));

        return [
            'as_of' => $asOfStr,
            'items' => $items,
            'totals' => array_values($totalsByCurrency),
        ];
    }

    /**
     * Estado de Cuenta de un cliente con saldo inicial, movimientos y saldo acumulado.
     *
     * @return array<string, mixed>
     */
    public function statement(Client $client, string $from, string $to, ?string $currencyId = null): array
    {
        $currency = $currencyId
            ? Currency::whereKey($currencyId)->firstOrFail()
            : Currency::where('code', 'PEN')->first() ?? Currency::firstOrFail();

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        // 1. Saldo inicial antes de $from
        $priorDebits = (string) Sale::query()
            ->where('client_id', $client->id)
            ->where('currency_id', $currency->id)
            ->where('payment_condition', PaymentCondition::Credit)
            ->where('status', '!=', SaleStatus::Cancelled)
            ->whereDate('issue_date', '<', $from)
            ->sum('total');

        $priorCredits = (string) CollectionAllocation::query()
            ->whereHas('collection', function (Builder $q) use ($client, $currency, $from): void {
                $q->where('client_id', $client->id)
                    ->where('currency_id', $currency->id)
                    ->where('status', CollectionStatus::Completed)
                    ->whereDate('payment_date', '<', $from);
            })
            ->sum('amount');

        $priorCreditNotes = (string) CreditNote::query()
            ->where('client_id', $client->id)
            ->where('currency_id', $currency->id)
            ->where('status', CreditNoteStatus::ISSUED)
            ->where('applied_to_balance', '>', 0)
            ->whereDate('issue_date', '<', $from)
            ->sum('applied_to_balance');

        $priorCredits = Decimal::add($priorCredits, $priorCreditNotes, 2);

        $openingBalance = Decimal::sub($priorDebits, $priorCredits, 2);

        // 2. Movimientos en el rango [$from, $to]
        $sales = Sale::query()
            ->where('client_id', $client->id)
            ->where('currency_id', $currency->id)
            ->where('payment_condition', PaymentCondition::Credit)
            ->where('status', '!=', SaleStatus::Cancelled)
            ->whereBetween('issue_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->get();

        $allocations = CollectionAllocation::query()
            ->with(['collection'])
            ->whereHas('collection', function (Builder $q) use ($client, $currency, $fromDate, $toDate): void {
                $q->where('client_id', $client->id)
                    ->where('currency_id', $currency->id)
                    ->where('status', CollectionStatus::Completed)
                    ->whereBetween('payment_date', [$fromDate->toDateString(), $toDate->toDateString()]);
            })
            ->get();

        $creditNotes = CreditNote::query()
            ->where('client_id', $client->id)
            ->where('currency_id', $currency->id)
            ->where('status', CreditNoteStatus::ISSUED)
            ->where('applied_to_balance', '>', 0)
            ->whereBetween('issue_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->get();

        /** @var list<array{id: string, date: string, datetime: string, type: string, number: string, concept: string, debit: string, credit: string, sort_timestamp: int}> $events */
        $events = [];

        foreach ($sales as $sale) {
            $events[] = [
                'id' => $sale->id,
                'date' => $sale->issue_date->toDateString(),
                'datetime' => $sale->issued_at->toIso8601String(),
                'type' => 'sale',
                'number' => $sale->full_number,
                'concept' => 'Venta a crédito',
                'debit' => (string) $sale->total,
                'credit' => '0.00',
                'sort_timestamp' => ((int) $sale->issue_date->timestamp) * 1000,
            ];
        }

        foreach ($allocations as $alloc) {
            $col = $alloc->collection;
            if ($col !== null) {
                $events[] = [
                    'id' => $col->id,
                    'date' => $col->payment_date->toDateString(),
                    'datetime' => $col->created_at->toIso8601String(),
                    'type' => 'collection',
                    'number' => $col->number,
                    'concept' => 'Cobranza',
                    'debit' => '0.00',
                    'credit' => (string) $alloc->amount,
                    'sort_timestamp' => ((int) $col->payment_date->timestamp) * 1000 + 1,
                ];
            }
        }

        foreach ($creditNotes as $cn) {
            $events[] = [
                'id' => $cn->id,
                'date' => Carbon::parse($cn->issue_date)->toDateString(),
                'datetime' => Carbon::parse($cn->issued_at)->toIso8601String(),
                'type' => 'credit_note',
                'number' => $cn->full_number,
                'concept' => "Nota de Crédito ({$cn->reason_description})",
                'debit' => '0.00',
                'credit' => (string) $cn->applied_to_balance,
                'sort_timestamp' => ((int) Carbon::parse($cn->issue_date)->timestamp) * 1000 + 2,
            ];
        }

        usort($events, fn (array $a, array $b): int => ((int) $a['sort_timestamp']) <=> ((int) $b['sort_timestamp']));

        $movements = [];
        $runningBalance = $openingBalance;
        $totalDebits = '0.00';
        $totalCredits = '0.00';

        foreach ($events as $ev) {
            $debit = $ev['debit'];
            $credit = $ev['credit'];

            $runningBalance = Decimal::add($runningBalance, $debit, 2);
            $runningBalance = Decimal::sub($runningBalance, $credit, 2);

            $totalDebits = Decimal::add($totalDebits, $debit, 2);
            $totalCredits = Decimal::add($totalCredits, $credit, 2);

            $movements[] = [
                'id' => $ev['id'],
                'date' => $ev['date'],
                'datetime' => $ev['datetime'],
                'type' => $ev['type'],
                'number' => $ev['number'],
                'concept' => $ev['concept'],
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
            ];
        }

        $closingBalance = $runningBalance;

        // 3. Cuotas pendientes al cierre del periodo
        $unpaidInstallments = ReceivableInstallment::query()
            ->with(['sale'])
            ->where('client_id', $client->id)
            ->where('currency_id', $currency->id)
            ->where('status', '!=', InstallmentStatus::Cancelled)
            ->where('balance', '>', 0)
            ->orderBy('due_date', 'asc')
            ->get();

        return [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'document_type' => $client->document_type?->value,
                'document_number' => $client->document_number,
                'address' => $client->address,
                'phone' => $client->phone,
                'credit_limit' => $client->credit_limit,
                'credit_days' => $client->credit_days,
            ],
            'currency' => [
                'id' => $currency->id,
                'code' => $currency->code,
                'symbol' => $currency->symbol,
            ],
            'from' => $from,
            'to' => $to,
            'opening_balance' => $openingBalance,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'closing_balance' => $closingBalance,
            'movements' => $movements,
            'unpaid_installments' => $unpaidInstallments->map(fn (ReceivableInstallment $inst) => [
                'id' => $inst->id,
                'sale_full_number' => $inst->sale?->full_number,
                'number' => $inst->number,
                'due_date' => $inst->due_date->toDateString(),
                'amount' => $inst->amount,
                'paid_amount' => $inst->paid_amount,
                'balance' => $inst->balance,
                'days_overdue' => $inst->due_date->lt(Carbon::today()) ? (int) $inst->due_date->diffInDays(Carbon::today()) : 0,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Collection>
     */
    public function collectionsQuery(array $filters = []): Builder
    {
        $query = Collection::query()
            ->with([
                'client',
                'currency',
                'paymentMethod',
                'branch',
                'creator',
                'allocations.installment',
                'allocations.sale',
            ]);

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['currency_id'])) {
            $query->where('currency_id', $filters['currency_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('payment_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('payment_date', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('number', 'ilike', "%{$search}%")
                    ->orWhereHas('client', fn (Builder $cq) => $cq->where('name', 'ilike', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('payment_date', 'desc')->orderBy('number', 'desc');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Collection>
     */
    public function collections(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, Collection> $paginator */
        $paginator = $this->collectionsQuery($filters)->paginate($perPage);

        return $paginator;
    }
}
