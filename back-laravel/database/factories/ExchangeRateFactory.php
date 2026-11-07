<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\ExchangeRate;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $buy = fake()->randomFloat(4, 3.5000, 3.9000);
        $sell = $buy + fake()->randomFloat(4, 0.0050, 0.0500);

        return [
            'currency_id' => Currency::factory(),
            'date' => fake()->unique()->date('Y-m-d'),
            'buy_rate' => number_format($buy, 4, '.', ''),
            'sell_rate' => number_format($sell, 4, '.', ''),
            'created_by' => User::factory(),
        ];
    }
}
