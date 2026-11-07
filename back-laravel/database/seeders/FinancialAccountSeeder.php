<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Finance\Support\AccountingPlanSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Plan de cuentas PCGE 2019 resumido. Idempotente: no pisa nombre ni estado de cuentas existentes.
 */
class FinancialAccountSeeder extends Seeder
{
    public function run(): void
    {
        $synchronizer = app(AccountingPlanSynchronizer::class);

        $synchronizer->syncAccounts(require database_path('data/financial_accounts_pcge.php'));
        $synchronizer->relocateConceptsFromParents(AccountingPlanSynchronizer::DEFAULT_LEAVES);
    }
}
