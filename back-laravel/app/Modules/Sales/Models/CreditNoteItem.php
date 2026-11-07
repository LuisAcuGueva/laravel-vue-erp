<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $credit_note_id
 * @property int $line
 * @property string|null $sale_item_id
 * @property string|null $catalog_item_id
 * @property string|null $item_presentation_id
 * @property string $description
 * @property string $quantity
 * @property string|null $base_quantity
 * @property string $unit_price
 * @property string $discount_percent
 * @property string $tax_type
 * @property string $line_subtotal
 * @property string $line_tax
 * @property string $line_total
 * @property bool $return_to_stock
 * @property string $unit_cost
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read CreditNote $creditNote
 * @property-read SaleItem|null $saleItem
 * @property-read CatalogItem|null $catalogItem
 * @property-read ItemPresentation|null $itemPresentation
 */
class CreditNoteItem extends Model
{
    use HasUuids;

    protected $table = 'credit_note_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'credit_note_id',
        'line',
        'sale_item_id',
        'catalog_item_id',
        'item_presentation_id',
        'description',
        'quantity',
        'base_quantity',
        'unit_price',
        'discount_percent',
        'tax_type',
        'line_subtotal',
        'line_tax',
        'line_total',
        'return_to_stock',
        'unit_cost',
    ];

    protected $casts = [
        'line' => 'integer',
        'quantity' => 'decimal:4',
        'base_quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'line_tax' => 'decimal:2',
        'line_total' => 'decimal:2',
        'return_to_stock' => 'boolean',
        'unit_cost' => 'decimal:6',
    ];

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    /**
     * @return BelongsTo<SaleItem, $this>
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
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
    public function itemPresentation(): BelongsTo
    {
        return $this->belongsTo(ItemPresentation::class, 'item_presentation_id');
    }
}
