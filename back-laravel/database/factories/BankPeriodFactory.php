<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankPeriod>
 */
class BankPeriodFactory extends Factory
{
    protected $model = BankPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'number' => 1,
            'status' => BankPeriodStatus::Open,
            'opened_at' => now()->startOfMonth()->toDateString(),
            'opening_balance' => 1000.00,
            'closed_at' => null,
            'closed_by' => null,
            'expected_balance' => null,
            'statement_balance' => null,
            'difference' => null,
            'notes' => null,
        ];
    }
}
