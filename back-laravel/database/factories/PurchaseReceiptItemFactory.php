<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Purchases\Models\PurchaseOrderItem;
use App\Modules\Purchases\Models\PurchaseReceipt;
use App\Modules\Purchases\Models\PurchaseReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReceiptItem>
 */
class PurchaseReceiptItemFactory extends Factory
{
    protected $model = PurchaseReceiptItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_receipt_id' => PurchaseReceipt::factory(),
            'line' => 1,
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'catalog_item_id' => CatalogItem::factory(),
            'item_presentation_id' => ItemPresentation::factory(),
            'is_service' => false,
            'quantity' => '1.0000',
            'base_quantity' => '1.0000',
            'unit_cost' => '10.0000',
            'base_unit_cost' => '10.000000',
            'total_cost' => '10.00',
            'notes' => null,
        ];
    }
}
