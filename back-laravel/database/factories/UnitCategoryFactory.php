<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Inventory\Models\UnitCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitCategory>
 */
class UnitCategoryFactory extends Factory
{
    protected $model = UnitCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
