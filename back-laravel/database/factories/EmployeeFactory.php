<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Maintenance\Models\Employee;
use App\Modules\Users\Models\User;
use App\Support\Identity\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type' => DocumentType::Dni,
            'document_number' => fake()->unique()->numerify('########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName().' '.fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('9########'),
            'address' => fake()->streetAddress(),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'hire_date' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'position' => fake()->jobTitle(),
            'area_id' => null,
            'branch_id' => null,
            'user_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }
}
