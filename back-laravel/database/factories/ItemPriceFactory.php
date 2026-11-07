<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\ItemPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemPrice>
 */
class ItemPriceFactory extends Factory
{
    protected $model = ItemPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_presentation_id' => ItemPresentation::factory(),
            'price_type' => PriceType::Retail,
            'price' => fake()->randomFloat(2, 5, 500),
            'min_quantity' => 1,
        ];
    }

    public function wholesale(float $minQuantity = 5.0): static
    {
        return $this->state(fn (array $attributes) => [
            'price_type' => PriceType::Wholesale,
            'min_quantity' => $minQuantity,
        ]);
    }
}
