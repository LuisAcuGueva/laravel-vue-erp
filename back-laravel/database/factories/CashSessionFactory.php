<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Enums\CashReviewStatus;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    protected $model = CashSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'CJ-'.fake()->unique()->numerify('######'),
            'box_id' => Box::factory(),
            'branch_id' => fn (array $attributes) => Box::find($attributes['box_id'])?->branch_id ?? Branch::factory(),
            'status' => CashSessionStatus::Open,
            'opened_by' => User::factory(),
            'opened_at' => now(),
            'opening_amount' => 100.00,
            'opening_notes' => null,
            'closed_by' => null,
            'closed_at' => null,
            'expected_cash' => null,
            'counted_cash' => null,
            'difference' => null,
            'closing_notes' => null,
            'review_status' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => CashSessionStatus::Closed,
            'closed_by' => User::factory(),
            'closed_at' => now(),
            'expected_cash' => 100.00,
            'counted_cash' => 100.00,
            'difference' => 0.00,
            'review_status' => CashReviewStatus::Approved,
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn () => [
            'status' => CashSessionStatus::Closed,
            'closed_by' => User::factory(),
            'closed_at' => now(),
            'expected_cash' => 100.00,
            'counted_cash' => 120.00,
            'difference' => 20.00,
            'closing_notes' => 'Sobrante de caja reportado',
            'review_status' => CashReviewStatus::Pending,
        ]);
    }
}
