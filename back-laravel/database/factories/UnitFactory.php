<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Inventory\Models\Unit;
use App\Modules\Inventory\Models\UnitCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_category_id' => UnitCategory::factory(),
            'name' => ucfirst(fake()->unique()->word()),
            'symbol' => fake()->unique()->lexify('??'),
            'is_base' => false,
            'conversion_factor' => '1.000000',
            'allow_decimals' => true,
            'sunat_code' => strtoupper(fake()->lexify('???')),
            'is_active' => true,
        ];
    }

    public function base(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_base' => true,
            'conversion_factor' => '1.000000',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
