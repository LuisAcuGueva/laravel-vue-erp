<?php

declare(strict_types=1);

namespace App\Modules\Sales\Listeners;

use App\Modules\Sales\Enums\InstallmentStatus;
use App\Modules\Sales\Events\SaleCancelled;
use App\Modules\Sales\Models\ReceivableInstallment;

class CancelReceivableInstallments
{
    public function handle(SaleCancelled $event): void
    {
        ReceivableInstallment::query()
            ->where('sale_id', $event->sale->id)
            ->where('status', '!=', InstallmentStatus::Cancelled)
            ->update([
                'status' => InstallmentStatus::Cancelled,
            ]);
    }
}
