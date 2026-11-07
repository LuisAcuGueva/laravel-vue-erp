<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $short = strtoupper(fake()->unique()->lexify('???'));

        return [
            'name' => 'Banco '.$short.' '.fake()->company(),
            'short_name' => $short,
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
