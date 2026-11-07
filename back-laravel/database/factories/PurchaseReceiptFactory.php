<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\ProviderDocumentType;
use App\Modules\Purchases\Enums\PurchaseReceiptStatus;
use App\Modules\Purchases\Models\Provider;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReceipt>
 */
class PurchaseReceiptFactory extends Factory
{
    protected $model = PurchaseReceipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'RC-'.fake()->unique()->numerify('######'),
            'branch_id' => Branch::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'provider_id' => Provider::factory(),
            'warehouse_id' => Warehouse::factory(),
            'receipt_date' => now()->toDateString(),
            'provider_document_type' => ProviderDocumentType::Guide,
            'provider_document_number' => 'GR-'.fake()->numerify('###-####'),
            'notes' => fake()->sentence(),
            'status' => PurchaseReceiptStatus::Draft,
            'total_cost' => '0.00',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseReceiptStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseReceiptStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => 'Anulación de prueba',
        ]);
    }
}
