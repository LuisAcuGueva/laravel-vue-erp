<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\ExchangeRate;
use App\Modules\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ExchangeRateService
{
    /**
     * @throws FinanceRuleException
     */
    public function upsert(
        string $currencyId,
        string $date,
        float $buyRate,
        float $sellRate,
        ?User $user = null
    ): ExchangeRate {
        $currency = Currency::query()->findOrFail($currencyId);

        if ($currency->is_base) {
            throw new FinanceRuleException('No se puede registrar tipo de cambio para la moneda base', 'currency_id');
        }

        if ($buyRate <= 0) {
            throw new FinanceRuleException('El tipo de cambio de compra debe ser mayor a 0', 'buy_rate');
        }

        if ($sellRate <= 0) {
            throw new FinanceRuleException('El tipo de cambio de venta debe ser mayor a 0', 'sell_rate');
        }

        if ($sellRate < $buyRate) {
            throw new FinanceRuleException('El tipo de cambio de venta debe ser mayor o igual al de compra', 'sell_rate');
        }

        /** @var ExchangeRate $rate */
        $rate = ExchangeRate::updateOrCreate(
            [
                'currency_id' => $currencyId,
                'date' => $date,
            ],
            [
                'buy_rate' => number_format($buyRate, 4, '.', ''),
                'sell_rate' => number_format($sellRate, 4, '.', ''),
                'created_by' => $user?->id,
            ]
        );

        return $rate->fresh(['currency', 'creator']) ?? $rate;
    }

    /**
     * Devuelve el tipo de cambio del día o el último anterior (con flag is_stale).
     *
     * @return array{rate: ExchangeRate|null, is_stale: bool}
     */
    public function current(string $currencyId, ?string $date = null): array
    {
        $targetDate = $date ?? Carbon::now()->toDateString();

        /** @var ExchangeRate|null $exact */
        $exact = ExchangeRate::with(['currency', 'creator'])
            ->where('currency_id', $currencyId)
            ->where('date', $targetDate)
            ->first();

        if ($exact !== null) {
            return [
                'rate' => $exact,
                'is_stale' => false,
            ];
        }

        /** @var ExchangeRate|null $previous */
        $previous = ExchangeRate::with(['currency', 'creator'])
            ->where('currency_id', $currencyId)
            ->where('date', '<', $targetDate)
            ->orderByDesc('date')
            ->first();

        return [
            'rate' => $previous,
            'is_stale' => true,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, ExchangeRate>
     */
    public function list(
        ?string $currencyId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 30
    ): LengthAwarePaginator {
        $query = ExchangeRate::with(['currency', 'creator']);

        if ($currencyId !== null && $currencyId !== '') {
            $query->where('currency_id', $currencyId);
        }

        if ($dateFrom !== null && trim($dateFrom) !== '') {
            $query->where('date', '>=', $dateFrom);
        }

        if ($dateTo !== null && trim($dateTo) !== '') {
            $query->where('date', '<=', $dateTo);
        }

        return $query->orderByDesc('date')->paginate($perPage);
    }

    /**
     * Convierte un monto entre dos monedas usando la moneda base como pivote.
     *
     * @throws FinanceRuleException
     */
    public function convert(
        float $amount,
        Currency $from,
        Currency $to,
        ?Carbon $date = null,
        string $side = 'sell'
    ): float {
        if ($from->id === $to->id) {
            return round($amount, $to->decimals);
        }

        $calcDate = ($date ?? Carbon::now())->toDateString();
        $base = Currency::base() ?? Currency::where('is_base', true)->firstOrFail();

        // 1. Convertir $from a base
        $amountInBase = $amount;
        if ($from->id !== $base->id) {
            $fromCurrent = $this->current($from->id, $calcDate);
            if ($fromCurrent['rate'] === null) {
                throw new FinanceRuleException('No hay tipo de cambio registrado para la moneda de origen', 'exchange_rate');
            }
            $rateValue = $side === 'buy' ? (float) $fromCurrent['rate']->buy_rate : (float) $fromCurrent['rate']->sell_rate;
            $amountInBase = $amount * $rateValue;
        }

        // 2. Convertir base a $to
        $amountInTarget = $amountInBase;
        if ($to->id !== $base->id) {
            $toCurrent = $this->current($to->id, $calcDate);
            if ($toCurrent['rate'] === null) {
                throw new FinanceRuleException('No hay tipo de cambio registrado para la moneda de destino', 'exchange_rate');
            }
            $rateValue = $side === 'buy' ? (float) $toCurrent['rate']->buy_rate : (float) $toCurrent['rate']->sell_rate;
            $amountInTarget = $amountInBase / $rateValue;
        }

        return round($amountInTarget, $to->decimals);
    }
}
