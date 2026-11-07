<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\BankPeriod;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankMovement>
 */
class BankMovementFactory extends Factory
{
    protected $model = BankMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'bank_period_id' => BankPeriod::factory(),
            'branch_id' => null,
            'direction' => MoneyDirection::In,
            'concept_id' => FinanceConcept::factory(),
            'payment_method_id' => null,
            'amount' => 100.00,
            'balance_after' => 1100.00,
            'operation_number' => fake()->numerify('OP-########'),
            'movement_date' => now()->toDateString(),
            'description' => fake()->sentence(3),
            'counterparty_name' => fake()->name(),
            'voucher_path' => null,
            'source_type' => null,
            'source_id' => null,
            'source_number' => null,
            'reverses_id' => null,
            'is_manual' => false,
            'created_by' => User::factory(),
        ];
    }
}
