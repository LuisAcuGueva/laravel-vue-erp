<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_session_id' => CashSession::factory(),
            'box_id' => fn (array $attributes) => CashSession::find($attributes['cash_session_id'])?->box_id ?? Box::factory(),
            'branch_id' => fn (array $attributes) => CashSession::find($attributes['cash_session_id'])?->branch_id ?? Branch::factory(),
            'direction' => MoneyDirection::In,
            'concept_id' => FinanceConcept::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'affects_cash' => true,
            'amount' => 50.00,
            'reference' => 'REC-'.fake()->numerify('####'),
            'description' => fake()->sentence(),
            'counterparty_name' => fake()->name(),
            'source_type' => null,
            'source_id' => null,
            'source_number' => null,
            'reverses_id' => null,
            'is_manual' => true,
            'created_by' => User::factory(),
            'moved_at' => now(),
            'created_at' => now(),
        ];
    }

    public function out(): static
    {
        return $this->state(fn () => [
            'direction' => MoneyDirection::Out,
        ]);
    }
}
