<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use Database\Factories\PurchaseReceiptItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $purchase_receipt_id
 * @property int $line
 * @property string $purchase_order_item_id
 * @property string|null $catalog_item_id
 * @property string|null $item_presentation_id
 * @property bool $is_service
 * @property string $quantity
 * @property string|null $base_quantity
 * @property string $unit_cost
 * @property string|null $base_unit_cost
 * @property string $total_cost
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PurchaseReceipt $receipt
 * @property-read PurchaseOrderItem $orderItem
 * @property-read CatalogItem|null $catalogItem
 * @property-read ItemPresentation|null $presentation
 */
class PurchaseReceiptItem extends Model
{
    /** @use HasFactory<PurchaseReceiptItemFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'purchase_receipt_items';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_service' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_receipt_id',
        'line',
        'purchase_order_item_id',
        'catalog_item_id',
        'item_presentation_id',
        'is_service',
        'quantity',
        'base_quantity',
        'unit_cost',
        'base_unit_cost',
        'total_cost',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'is_service' => 'boolean',
            'quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'base_unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PurchaseReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    /**
     * @return BelongsTo<ItemPresentation, $this>
     */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ItemPresentation::class, 'item_presentation_id');
    }

    protected static function newFactory(): PurchaseReceiptItemFactory
    {
        return PurchaseReceiptItemFactory::new();
    }
}
