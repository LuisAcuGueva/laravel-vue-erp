<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Support\AccountingPlanSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Conceptos de sistema y operativos con su cuenta contrapartida. Depende de FinancialAccountSeeder.
 */
class FinanceConceptSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SystemConcept::cases() as $concept) {
            $model = FinanceConcept::firstOrNew(['code' => $concept->value]);

            if (! $model->exists) {
                $model->name = $concept->defaultName();
            }

            $model->direction = $concept->direction();
            $model->is_system = true;
            $model->is_active = true;
            $model->save();
        }

        app(AccountingPlanSynchronizer::class)->assignConceptAccounts();
    }
}
