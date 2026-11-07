<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialAccount>
 */
class FinancialAccountFactory extends Factory
{
    protected $model = FinancialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numerify('9#'),
            'name' => ucfirst(fake()->words(3, true)),
            'type' => FinancialAccountType::Asset,
            'parent_id' => null,
            'level' => 1,
            'is_postable' => true,
            'is_active' => true,
        ];
    }

    public function postable(bool $postable = true): static
    {
        return $this->state(fn () => [
            'is_postable' => $postable,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
