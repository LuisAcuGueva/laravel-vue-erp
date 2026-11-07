<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BankQueryService
{
    /**
     * @param  Collection<int, BankAccount>  $accounts
     * @return array<string, mixed>
     */
    public function summary(Collection $accounts): array
    {
        $accounts->load(['bank', 'currency', 'openPeriod']);

        /** @var \Illuminate\Support\Collection<string, Collection<int, BankAccount>> $byCurrency */
        $byCurrency = $accounts->groupBy('currency_id');

        $result = [];

        foreach ($byCurrency as $currencyAccounts) {
            /** @var BankAccount|null $first */
            $first = $currencyAccounts->first();
            if ($first === null || $first->currency === null) {
                continue;
            }
            $currency = $first->currency;

            $total = 0.0;
            $items = [];

            foreach ($currencyAccounts as $acc) {
                $total += (float) $acc->balance;
                $openPeriod = $acc->openPeriod;

                $items[] = [
                    'id' => $acc->id,
                    'bank' => $acc->bank !== null ? [
                        'id' => $acc->bank->id,
                        'name' => $acc->bank->name,
                        'short_name' => $acc->bank->short_name,
                    ] : [
                        'id' => '',
                        'name' => '',
                        'short_name' => '',
                    ],
                    'alias' => $acc->alias,
                    'account_number' => $acc->account_number,
                    'account_type' => $acc->account_type,
                    'holder_name' => $acc->holder_name,
                    'currency' => [
                        'id' => $currency->id,
                        'code' => $currency->code,
                        'symbol' => $currency->symbol,
                    ],
                    'balance' => (float) $acc->balance,
                    'current_period' => $openPeriod !== null ? [
                        'id' => $openPeriod->id,
                        'number' => $openPeriod->number,
                        'opened_at' => $openPeriod->opened_at->toDateString(),
                    ] : null,
                    'periods_count' => $acc->periods()->count(),
                ];
            }

            $result[] = [
                'currency' => [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                ],
                'total' => (float) number_format($total, 2, '.', ''),
                'accounts' => $items,
            ];
        }

        return [
            'by_currency' => $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{opening: float, movements: LengthAwarePaginator<int, BankMovement>, closing: float}
     */
    public function movements(
        BankAccount $account,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        array $filters = [],
        int $perPage = 15
    ): array {
        if ($dateFrom === null) {
            $currentPeriod = $account->openPeriod;
            $dateFrom = $currentPeriod !== null
                ? $currentPeriod->opened_at->toDateString()
                : now()->startOfMonth()->toDateString();
        }

        if ($dateTo === null) {
            $dateTo = now()->toDateString();
        }

        /** @var BankMovement|null $lastPrior */
        $lastPrior = BankMovement::where('bank_account_id', $account->id)
            ->where('movement_date', '<', $dateFrom)
            ->orderByDesc('movement_date')
            ->orderByDesc('created_at')
            ->first();

        if ($lastPrior !== null) {
            $opening = (float) $lastPrior->balance_after;
        } else {
            $firstPeriod = $account->periods()->orderBy('number')->first();
            $opening = $firstPeriod !== null ? (float) $firstPeriod->opening_balance : 0.0;
        }

        $query = BankMovement::with(['concept', 'paymentMethod', 'creator', 'reversal'])
            ->where('bank_account_id', $account->id)
            ->whereBetween('movement_date', [$dateFrom, $dateTo]);

        if (! empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (! empty($filters['concept_id'])) {
            $query->where('concept_id', $filters['concept_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('operation_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('counterparty_name', 'like', "%{$search}%");
            });
        }

        $query->orderByDesc('movement_date')->orderByDesc('created_at');

        /** @var LengthAwarePaginator<int, BankMovement> $paginator */
        $paginator = $query->paginate($perPage);

        /** @var BankMovement|null $lastInRange */
        $lastInRange = BankMovement::where('bank_account_id', $account->id)
            ->where('movement_date', '<=', $dateTo)
            ->orderByDesc('movement_date')
            ->orderByDesc('created_at')
            ->first();

        $closing = $lastInRange !== null ? (float) $lastInRange->balance_after : $opening;

        return [
            'opening' => $opening,
            'movements' => $paginator,
            'closing' => $closing,
        ];
    }
}
