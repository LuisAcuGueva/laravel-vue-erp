<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Finance\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceConcept>
 */
class FinanceConceptFactory extends Factory
{
    protected $model = FinanceConcept::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('CON_???')),
            'name' => ucfirst(fake()->words(2, true)),
            'direction' => ConceptDirection::Income,
            'financial_account_id' => null,
            'is_system' => false,
            'is_active' => true,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn () => [
            'direction' => ConceptDirection::Expense,
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn () => [
            'direction' => ConceptDirection::Transfer,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn () => [
            'is_system' => true,
        ]);
    }

    public function withAccount(FinancialAccount $account): static
    {
        return $this->state(fn () => [
            'financial_account_id' => $account->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
