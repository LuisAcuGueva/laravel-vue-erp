<?php

declare(strict_types=1);

namespace App\Modules\Finance\Console;

use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\BankPeriod;
use Illuminate\Console\Command;

class VerifyBankBalancesCommand extends Command
{
    protected $signature = 'finance:verify-bank-balances';

    protected $description = 'Verifica la integridad de los saldos bancarios recalculándolos desde el ledger';

    public function handle(): int
    {
        $this->info('Verificando saldos de cuentas bancarias...');

        $accounts = BankAccount::with(['bank', 'currency'])->get();
        $hasErrors = false;

        $rows = [];

        foreach ($accounts as $account) {
            /** @var BankPeriod|null $firstPeriod */
            $firstPeriod = BankPeriod::where('bank_account_id', $account->id)
                ->orderBy('number')
                ->first();

            $opening = $firstPeriod !== null ? (float) $firstPeriod->opening_balance : 0.0;
            $in = (float) BankMovement::where('bank_account_id', $account->id)
                ->where('direction', MoneyDirection::In->value)
                ->sum('amount');
            $out = (float) BankMovement::where('bank_account_id', $account->id)
                ->where('direction', MoneyDirection::Out->value)
                ->sum('amount');

            $calculated = $opening + $in - $out;
            $actual = (float) $account->balance;
            $diff = abs($calculated - $actual);

            $status = $diff < 0.001 ? 'OK' : 'DISCREPANCIA';
            if ($status !== 'OK') {
                $hasErrors = true;
            }

            $rows[] = [
                $account->alias,
                $account->account_number,
                $account->currency->code ?? '',
                number_format($opening, 2),
                number_format($in, 2),
                number_format($out, 2),
                number_format($calculated, 2),
                number_format($actual, 2),
                $status,
            ];
        }

        $this->table(
            ['Alias', 'Número', 'Moneda', 'Apertura', 'Total In', 'Total Out', 'Calculado', 'Actual', 'Estado'],
            $rows
        );

        if ($hasErrors) {
            $this->error('Se encontraron discrepancias en los saldos bancarios.');

            return self::FAILURE;
        }

        $this->info('Todos los saldos bancarios concuerdan con el ledger.');

        return self::SUCCESS;
    }
}
