<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Inventory\Enums\WarehouseReasonType;
use App\Modules\Inventory\Models\WarehouseReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseReason>
 */
class WarehouseReasonFactory extends Factory
{
    protected $model = WarehouseReason::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('RSN_????')),
            'name' => ucfirst(fake()->words(3, true)),
            'type' => WarehouseReasonType::In,
            'requires_approval' => true,
            'is_system' => false,
            'is_active' => true,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
            'requires_approval' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
