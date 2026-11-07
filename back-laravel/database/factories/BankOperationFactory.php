<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Enums\BankOperationStatus;
use App\Modules\Finance\Enums\BankOperationType;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankOperation;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankOperation>
 */
class BankOperationFactory extends Factory
{
    protected $model = BankOperation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'BO-'.fake()->unique()->numerify('######'),
            'type' => BankOperationType::Deposit,
            'status' => BankOperationStatus::Completed,
            'bank_account_id' => BankAccount::factory(),
            'target_bank_account_id' => null,
            'cash_session_id' => null,
            'amount' => 100.00,
            'target_amount' => null,
            'exchange_rate' => null,
            'operation_number' => fake()->numerify('OP-########'),
            'operation_date' => now()->toDateString(),
            'notes' => fake()->sentence(3),
            'voucher_path' => null,
            'created_by' => User::factory(),
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
        ];
    }
}
