<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Support\Pricing\Decimal;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $purchase_order_id
 * @property int $line
 * @property string|null $purchase_request_item_id
 * @property string|null $catalog_item_id
 * @property string|null $item_presentation_id
 * @property string|null $description
 * @property bool $is_service
 * @property string $quantity
 * @property string $received_quantity
 * @property string $unit_price
 * @property string $discount_percent
 * @property TaxType $tax_type
 * @property string $line_subtotal
 * @property string $line_tax
 * @property string $line_total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $pending_quantity
 * @property-read PurchaseOrder $order
 * @property-read PurchaseRequestItem|null $requestItem
 * @property-read CatalogItem|null $catalogItem
 * @property-read ItemPresentation|null $presentation
 */
class PurchaseOrderItem extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'purchase_order_items';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'received_quantity' => 0,
        'discount_percent' => 0,
        'is_service' => false,
        'tax_type' => 'taxable',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_order_id',
        'line',
        'purchase_request_item_id',
        'catalog_item_id',
        'item_presentation_id',
        'description',
        'is_service',
        'quantity',
        'received_quantity',
        'unit_price',
        'discount_percent',
        'tax_type',
        'line_subtotal',
        'line_tax',
        'line_total',
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
            'received_quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'tax_type' => TaxType::class,
            'line_subtotal' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Cantidad que aún falta recibir (la recepción la registra el spec 023).
     *
     * @return Attribute<string, never>
     */
    protected function pendingQuantity(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Decimal::sub((string) $this->quantity, (string) $this->received_quantity, 4),
        );
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * @return BelongsTo<PurchaseRequestItem, $this>
     */
    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
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
}
