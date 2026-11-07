<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemPresentation>
 */
class ItemPresentationFactory extends Factory
{
    protected $model = ItemPresentation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catalog_item_id' => CatalogItem::factory(),
            'unit_id' => Unit::factory(),
            'name' => ucfirst(fake()->word()),
            'factor' => 1.0000,
            'barcode' => fake()->unique()->ean13(),
            'is_base' => false,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function base(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_base' => true,
            'is_default' => true,
            'factor' => 1.0000,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
