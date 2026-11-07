<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Finance\Exceptions\BankLedgerException;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Users\Models\User;
use App\Support\Pricing\Decimal;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BankPeriodService
{
    public function openInitial(
        BankAccount $account,
        string|float $openingBalance,
        DateTimeInterface|string $openedAt,
        User $user
    ): BankPeriod {
        return DB::transaction(function () use ($account, $openingBalance, $openedAt) {
            /** @var BankAccount $lockedAccount */
            $lockedAccount = BankAccount::where('id', $account->id)->lockForUpdate()->firstOrFail();

            if ($lockedAccount->periods()->exists()) {
                throw new BankLedgerException('La cuenta bancaria ya tiene periodos registrados.', 422);
            }

            $openingStr = number_format((float) $openingBalance, 2, '.', '');
            $openedAtStr = Carbon::parse($openedAt)->toDateString();

            /** @var BankPeriod $period */
            $period = BankPeriod::create([
                'bank_account_id' => $lockedAccount->id,
                'number' => 1,
                'status' => BankPeriodStatus::Open,
                'opened_at' => $openedAtStr,
                'opening_balance' => $openingStr,
            ]);

            $lockedAccount->forceFill(['balance' => $openingStr])->save();

            return $period;
        });
    }

    /**
     * @return array{closed: BankPeriod, next: BankPeriod}
     */
    public function close(
        BankPeriod $period,
        string|float $statementBalance,
        DateTimeInterface|string $closedAt,
        ?string $notes,
        User $user
    ): array {
        return DB::transaction(function () use ($period, $statementBalance, $closedAt, $notes, $user) {
            /** @var BankPeriod $lockedPeriod */
            $lockedPeriod = BankPeriod::where('id', $period->id)->lockForUpdate()->firstOrFail();

            if ($lockedPeriod->status !== BankPeriodStatus::Open) {
                throw new BankLedgerException('El periodo no está abierto.', 422);
            }

            /** @var BankAccount $lockedAccount */
            $lockedAccount = BankAccount::where('id', $lockedPeriod->bank_account_id)->lockForUpdate()->firstOrFail();

            $closedAtCarbon = Carbon::parse($closedAt)->startOfDay();
            $openedAtCarbon = Carbon::parse($lockedPeriod->opened_at)->startOfDay();

            if ($closedAtCarbon->lt($openedAtCarbon)) {
                throw new BankLedgerException('La fecha de cierre debe ser igual o posterior a la fecha de apertura.', 422);
            }

            $expected = (string) $lockedAccount->balance;
            $statement = number_format((float) $statementBalance, 2, '.', '');
            $difference = Decimal::sub($statement, $expected, 2);

            if ((float) $difference !== 0.0 && ($notes === null || trim($notes) === '')) {
                throw new BankLedgerException('Se requiere una nota explicativa cuando existe diferencia con el estado de cuenta.', 422);
            }

            $lockedPeriod->update([
                'status' => BankPeriodStatus::Closed,
                'closed_at' => $closedAtCarbon->toDateString(),
                'closed_by' => $user->id,
                'expected_balance' => $expected,
                'statement_balance' => $statement,
                'difference' => $difference,
                'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            ]);

            $nextOpenedAt = $closedAtCarbon->copy()->addDay()->toDateString();

            /** @var BankPeriod $nextPeriod */
            $nextPeriod = BankPeriod::create([
                'bank_account_id' => $lockedAccount->id,
                'number' => $lockedPeriod->number + 1,
                'status' => BankPeriodStatus::Open,
                'opened_at' => $nextOpenedAt,
                'opening_balance' => $expected,
            ]);

            return [
                'closed' => $lockedPeriod,
                'next' => $nextPeriod,
            ];
        });
    }
}
