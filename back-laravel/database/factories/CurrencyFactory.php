<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->currencyCode().' Currency',
            'symbol' => fake()->randomElement(['$', '€', 'S/', '¥', '£']),
            'sunat_code' => fake()->numerify('###'),
            'decimals' => 2,
            'is_base' => false,
            'is_active' => true,
        ];
    }

    public function base(): static
    {
        return $this->state(fn () => [
            'is_base' => true,
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
