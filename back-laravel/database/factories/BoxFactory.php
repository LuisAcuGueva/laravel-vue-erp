<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Models\Box;
use App\Modules\Maintenance\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Box>
 */
class BoxFactory extends Factory
{
    protected $model = Box::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code' => 'BOX-'.fake()->unique()->numerify('####'),
            'name' => 'Caja '.fake()->word(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
