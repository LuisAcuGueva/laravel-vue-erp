<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Purchases\Models\Provider;
use App\Support\Identity\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type' => DocumentType::Ruc,
            'document_number' => '20'.fake()->unique()->numerify('#########'),
            'business_name' => fake()->company().' S.A.C.',
            'trade_name' => fake()->companySuffix(),
            'address' => fake()->streetAddress(),
            'ubigeo' => null,
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('9########'),
            'website' => null,
            'default_currency_id' => null,
            'credit_days' => 0,
            'notes' => null,
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
