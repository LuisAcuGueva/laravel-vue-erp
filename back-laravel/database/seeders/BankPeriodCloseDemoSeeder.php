<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Services\BankLedgerService;
use App\Modules\Finance\Services\BankPeriodService;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Cierra el periodo #1 de "BBVA Soles" con una diferencia frente al estado de
 * cuenta (fuerza la nota obligatoria) y verifica la apertura automática del
 * periodo #2. Debe ejecutarse al final, después de todos los movimientos
 * bancarios demo. Es idempotente.
 *
 * Depende de: BankOperationsDemoSeeder.
 */
class BankPeriodCloseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $account = BankAccount::where('alias', 'BBVA Soles')->first();

        if ($admin === null || $account === null) {
            return;
        }

        /** @var BankPeriod|null $period */
        $period = app(BankLedgerService::class)->currentPeriod($account);

        if ($period === null || $period->number !== 1) {
            return;
        }

        $statementBalance = round((float) $account->balance - 8.50, 2);

        app(BankPeriodService::class)->close(
            $period,
            $statementBalance,
            Carbon::today(),
            'Diferencia por comisión de mantenimiento no contabilizada (DEMO-BANK-PERIOD-CLOSE).',
            $admin
        );
    }
}
