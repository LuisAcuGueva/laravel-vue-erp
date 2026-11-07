<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Enums\CashTransferStatus;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\CashTransfer;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashTransfer>
 */
class CashTransferFactory extends Factory
{
    protected $model = CashTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'TC-'.fake()->unique()->numerify('######'),
            'status' => CashTransferStatus::Pending,
            'source_session_id' => CashSession::factory(),
            'source_box_id' => fn (array $attributes) => CashSession::find($attributes['source_session_id'])?->box_id ?? Box::factory(),
            'source_branch_id' => fn (array $attributes) => CashSession::find($attributes['source_session_id'])?->branch_id ?? Branch::factory(),
            'target_box_id' => Box::factory(),
            'target_branch_id' => fn (array $attributes) => Box::find($attributes['target_box_id'])?->branch_id ?? Branch::factory(),
            'target_session_id' => null,
            'amount' => 50.00,
            'reason' => fake()->sentence(4),
            'created_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => CashTransferStatus::Approved,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => CashTransferStatus::Rejected,
            'rejected_by' => User::factory(),
            'rejected_at' => now(),
            'rejection_reason' => 'Rechazado por supervisión',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => CashTransferStatus::Cancelled,
            'cancelled_by' => fn (array $attributes) => $attributes['created_by'],
            'cancelled_at' => now(),
            'cancel_reason' => 'Cancelado por emisor',
        ]);
    }
}
