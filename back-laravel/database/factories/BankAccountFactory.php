<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\Currency;
use App\Modules\Maintenance\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_id' => Bank::factory(),
            'currency_id' => Currency::factory(),
            'branch_id' => Branch::factory(),
            'alias' => ucfirst(fake()->words(3, true)),
            'account_number' => fake()->unique()->numerify('################'),
            'cci' => fake()->numerify('####################'),
            'account_type' => BankAccountType::Checking,
            'holder_name' => fake()->company(),
            'balance' => 0,
            'is_active' => true,
        ];
    }

    public function savings(): static
    {
        return $this->state(fn () => [
            'account_type' => BankAccountType::Savings,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
