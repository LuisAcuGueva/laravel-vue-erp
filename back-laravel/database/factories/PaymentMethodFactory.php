<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Finance\Enums\Settlement;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('PM_???')),
            'name' => ucfirst(fake()->words(2, true)),
            'kind' => PaymentMethodKind::Transfer,
            'settlement' => Settlement::BankAccount,
            'bank_account_id' => BankAccount::factory(),
            'requires_reference' => false,
            'sunat_code' => fake()->numerify('###'),
            'sort_order' => 0,
            'is_system' => false,
            'is_active' => true,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'code' => 'CASH',
            'name' => 'Efectivo',
            'kind' => PaymentMethodKind::Cash,
            'settlement' => Settlement::CashBox,
            'bank_account_id' => null,
            'sunat_code' => '008',
            'is_system' => true,
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
