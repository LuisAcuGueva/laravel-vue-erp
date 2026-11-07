<?php

declare(strict_types=1);

namespace App\Modules\Sales\Hooks;

use App\Modules\Sales\Contracts\AfterSaleIssuedHook;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\InstallmentScheduleService;

class CreateReceivableInstallments implements AfterSaleIssuedHook
{
    public function __construct(
        private readonly InstallmentScheduleService $scheduleService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Sale $sale, array $input): void
    {
        if ($sale->payment_condition !== PaymentCondition::Credit) {
            return;
        }

        if ((float) $sale->balance <= 0) {
            return;
        }

        /** @var array<int, array{due_date: string, amount: numeric-string|float|int}>|null $explicit */
        $explicit = $input['installments'] ?? null;
        $count = isset($input['installments_count']) ? (int) $input['installments_count'] : null;
        $intervalDays = isset($input['installment_interval_days']) ? (int) $input['installment_interval_days'] : null;

        $schedule = $this->scheduleService->build($sale, $explicit, $count, $intervalDays);

        if ($explicit !== null && count($explicit) > 0) {
            $client = $sale->relationLoaded('client') ? $sale->client : $sale->client()->firstOrFail();
            $this->scheduleService->validate($sale, $schedule, $client);
        }

        $this->scheduleService->createFor($sale, $schedule);
    }
}
