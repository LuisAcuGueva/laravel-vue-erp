<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Maintenance\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('BR-????')),
            'name' => fake()->unique()->company().' Sede',
            'address' => fake()->address(),
            'ubigeo' => '150101',
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'is_main' => false,
            'is_active' => true,
        ];
    }

    public function main(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_main' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
