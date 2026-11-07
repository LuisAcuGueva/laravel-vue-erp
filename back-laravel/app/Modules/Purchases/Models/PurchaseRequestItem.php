<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

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
 * @property string $purchase_request_id
 * @property int $line
 * @property string|null $catalog_item_id
 * @property string|null $item_presentation_id
 * @property string|null $description
 * @property string $quantity
 * @property string $ordered_quantity
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $pending_quantity
 * @property-read PurchaseRequest $request
 * @property-read CatalogItem|null $catalogItem
 * @property-read ItemPresentation|null $presentation
 */
class PurchaseRequestItem extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'purchase_request_items';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ordered_quantity' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_request_id',
        'line',
        'catalog_item_id',
        'item_presentation_id',
        'description',
        'quantity',
        'ordered_quantity',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'quantity' => 'decimal:4',
            'ordered_quantity' => 'decimal:4',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Cantidad que aún se puede incluir en órdenes de compra.
     *
     * @return Attribute<string, never>
     */
    protected function pendingQuantity(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Decimal::sub((string) $this->quantity, (string) $this->ordered_quantity, 4),
        );
    }

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
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
