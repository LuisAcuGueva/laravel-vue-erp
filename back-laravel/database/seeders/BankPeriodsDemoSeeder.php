<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Services\BankPeriodService;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Abre el periodo bancario inicial de las cuentas demo que aún no lo tienen
 * ("BCP Soles" ya lo abre SupplierInvoicesDemoSeeder). Es idempotente:
 * BankPeriodService::openInitial() no hace nada si la cuenta ya tiene periodos.
 *
 * Depende de: FinanceDemoSeeder (cuentas bancarias), SupplierInvoicesDemoSeeder.
 */
class BankPeriodsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first() ?? User::first();
        if ($admin === null) {
            return;
        }

        $openedAt = Carbon::now()->startOfMonth();
        $service = app(BankPeriodService::class);

        $initialBalances = [
            'BCP Dólares' => '5000.00',
            'BBVA Soles' => '20000.00',
            'Interbank Soles' => '15000.00',
        ];

        foreach ($initialBalances as $alias => $opening) {
            /** @var BankAccount|null $account */
            $account = BankAccount::where('alias', $alias)->first();

            if ($account === null || $account->periods()->exists()) {
                continue;
            }

            $service->openInitial($account, $opening, $openedAt, $admin);
        }
    }
}
