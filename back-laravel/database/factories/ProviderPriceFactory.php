<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Models\Currency;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\ProviderPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderPrice>
 */
class ProviderPriceFactory extends Factory
{
    protected $model = ProviderPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'item_presentation_id' => ItemPresentation::factory(),
            'currency_id' => Currency::factory(),
            'price' => fake()->randomFloat(4, 1, 500),
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => null,
            'notes' => null,
        ];
    }
}
