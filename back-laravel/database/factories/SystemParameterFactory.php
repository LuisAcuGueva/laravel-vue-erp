<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Configuration\Enums\ParameterValueType;
use App\Modules\Configuration\Models\SystemParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemParameter>
 */
class SystemParameterFactory extends Factory
{
    protected $model = SystemParameter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_key' => fake()->slug(1),
            'item_key' => fake()->unique()->slug(2),
            'label' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'value_type' => ParameterValueType::String,
            'value' => fake()->word(),
            'is_editable' => true,
            'is_active' => true,
        ];
    }

    public function boolean(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_type' => ParameterValueType::Boolean,
            'value' => true,
        ]);
    }

    public function decimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_type' => ParameterValueType::Decimal,
            'value' => 10.50,
        ]);
    }

    public function integer(): static
    {
        return $this->state(fn (array $attributes) => [
            'value_type' => ParameterValueType::Integer,
            'value' => 15,
        ]);
    }

    public function notEditable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_editable' => false,
        ]);
    }
}
