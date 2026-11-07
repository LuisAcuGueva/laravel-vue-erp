<?php

declare(strict_types=1);

namespace App\Modules\Sales\Console;

use App\Modules\Sales\Services\QuotationWorkflowService;
use Illuminate\Console\Command;

class ExpireQuotationsCommand extends Command
{
    protected $signature = 'sales:expire-quotations';

    protected $description = 'Pasa a estado vencida (expired) las cotizaciones enviadas cuya fecha de validez ha expirado';

    public function handle(QuotationWorkflowService $workflowService): int
    {
        $this->info('Evaluando cotizaciones enviadas vencidas...');

        $expiredCount = $workflowService->expireOverdue();

        $this->info("Se marcaron {$expiredCount} cotizaciones como vencidas.");

        return self::SUCCESS;
    }
}
