<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Models\Currency;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Sales\Enums\QuotationStatus;
use App\Modules\Sales\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issueDate = fake()->dateTimeBetween('-1 month', 'now');
        $validUntil = (clone $issueDate)->modify('+15 days');

        return [
            'number' => 'COT-'.fake()->unique()->numerify('######'),
            'branch_id' => Branch::factory(),
            'client_id' => Client::factory(),
            'contact_id' => null,
            'seller_id' => null,
            'issue_date' => $issueDate->format('Y-m-d'),
            'valid_until' => $validUntil->format('Y-m-d'),
            'currency_id' => Currency::factory(),
            'exchange_rate' => 1.0,
            'price_type' => PriceType::Retail->value,
            'prices_include_tax' => true,
            'tax_rate' => 18.00,
            'global_discount_percent' => 0.00,
            'taxable_base' => 100.00,
            'exempt_base' => 0.00,
            'unaffected_base' => 0.00,
            'discount_total' => 0.00,
            'tax_total' => 18.00,
            'total' => 118.00,
            'total_base_currency' => 118.00,
            'status' => QuotationStatus::Draft,
            'notes' => fake()->optional()->sentence(),
            'terms' => 'Precios incluyen IGV. Pago al contado.',
            'delivery_time' => '3 días hábiles',
            'payment_terms' => 'Contado',
            'discount_override_by' => null,
            'created_by' => null,
            'sent_at' => null,
            'accepted_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
            'converted_sale_id' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => QuotationStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => QuotationStatus::Accepted,
            'sent_at' => now()->subDay(),
            'accepted_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Precio elevado'): static
    {
        return $this->state(fn () => [
            'status' => QuotationStatus::Rejected,
            'sent_at' => now()->subDay(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function cancelled(string $reason = 'Cliente desistió'): static
    {
        return $this->state(fn () => [
            'status' => QuotationStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => QuotationStatus::Expired,
            'issue_date' => now()->subDays(20)->format('Y-m-d'),
            'valid_until' => now()->subDays(5)->format('Y-m-d'),
            'sent_at' => now()->subDays(19),
        ]);
    }
}
