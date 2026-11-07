<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Clients\Enums\ClientType;
use App\Modules\Clients\Models\Client;
use App\Modules\Inventory\Enums\PriceType;
use App\Support\Identity\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_type' => ClientType::Person,
            'document_type' => DocumentType::Dni,
            'document_number' => (string) fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'trade_name' => null,
            'representative_name' => null,
            'representative_document' => null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'ubigeo' => null,
            'default_price_type' => PriceType::Retail,
            'description' => fake()->sentence(),
            'credit_limit' => 0,
            'credit_days' => 0,
            'is_system' => false,
            'is_active' => true,
        ];
    }

    public function person(): static
    {
        return $this->state(fn () => [
            'client_type' => ClientType::Person,
            'document_type' => DocumentType::Dni,
            'document_number' => (string) fake()->unique()->numerify('########'),
            'name' => fake()->name(),
        ]);
    }

    public function company(): static
    {
        return $this->state(fn () => [
            'client_type' => ClientType::Company,
            'document_type' => DocumentType::Ruc,
            'document_number' => '20'.fake()->unique()->numerify('#########'),
            'name' => fake()->company().' S.A.C.',
            'trade_name' => fake()->company(),
            'default_price_type' => PriceType::Wholesale,
        ]);
    }

    public function withCredit(float $limit = 5000.00, int $days = 30): static
    {
        return $this->state(fn () => [
            'credit_limit' => $limit,
            'credit_days' => $days,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn () => [
            'client_type' => ClientType::Person,
            'document_type' => DocumentType::Other,
            'document_number' => '00000000',
            'name' => 'CLIENTES VARIOS',
            'is_system' => true,
            'credit_limit' => 0,
            'credit_days' => 0,
            'is_active' => true,
        ]);
    }
}
