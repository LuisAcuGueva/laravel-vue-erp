<?php

declare(strict_types=1);

use App\Modules\Finance\Support\AccountingPlanSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array{code: string, parent: ?string}> */
    private const RENAMES = [
        '10.1' => ['code' => '101', 'parent' => '10'],
        '10.4' => ['code' => '104', 'parent' => '10'],
        '12.1' => ['code' => '121', 'parent' => '12'],
        '42.1' => ['code' => '421', 'parent' => '42'],
        '65.1' => ['code' => '636', 'parent' => '63'],
        '65.2' => ['code' => '635', 'parent' => '63'],
        '70.1' => ['code' => '701', 'parent' => '70'],
        '70.4' => ['code' => '704', 'parent' => '70'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('financial_accounts') || ! Schema::hasTable('finance_concepts')) {
            return;
        }

        // Instalación nueva: el plan lo siembra FinancialAccountSeeder.
        if (! DB::table('financial_accounts')->exists()) {
            return;
        }

        DB::transaction(function () {
            $sync = app(AccountingPlanSynchronizer::class);

            // 1. Renombra conservando el id; si el destino ya existe, fusiona.
            //    El padre destino puede no existir aún: el paso 2 lo crea y re-parenta.
            foreach (self::RENAMES as $old => $target) {
                $sync->renameOrMerge($old, $target['code'], $target['parent']);
            }

            // 2. Completa el catálogo y recalcula jerarquía, niveles e imputabilidad.
            $sync->syncAccounts(require database_path('data/financial_accounts_pcge.php'));

            // 3. Mueve los conceptos que quedaron en cuentas que dejaron de ser imputables.
            $sync->relocateConceptsFromParents(AccountingPlanSynchronizer::DEFAULT_LEAVES);
        });
    }

    public function down(): void
    {
        // Migración de datos irreversible: los códigos con punto no son válidos desde el spec 032.
    }
};
