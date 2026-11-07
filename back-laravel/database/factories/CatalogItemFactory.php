<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Inventory\Enums\CatalogItemType;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\Brand;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\Category;
use App\Modules\Inventory\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    protected $model = CatalogItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'ITM-'.fake()->unique()->numerify('######'),
            'name' => ucfirst(fake()->words(3, true)),
            'type' => CatalogItemType::Product,
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'base_unit_id' => Unit::factory(),
            'description' => fake()->paragraph(),
            'tax_type' => TaxType::Taxable,
            'track_stock' => true,
            'is_sellable' => true,
            'is_purchasable' => true,
            'is_active' => true,
            'last_purchase_cost' => null,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CatalogItemType::Service,
            'track_stock' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
