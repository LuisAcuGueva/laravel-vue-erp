<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $quotation_id
 * @property int $line
 * @property string|null $catalog_item_id
 * @property string|null $item_presentation_id
 * @property string $description
 * @property string $quantity
 * @property string|null $list_price
 * @property string $unit_price
 * @property string $discount_percent
 * @property string $effective_discount_percent
 * @property TaxType $tax_type
 * @property string $line_subtotal
 * @property string $line_tax
 * @property string $line_total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Quotation $quotation
 * @property-read CatalogItem|null $catalogItem
 * @property-read ItemPresentation|null $presentation
 */
class QuotationItem extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'quotation_items';

    protected $fillable = [
        'quotation_id',
        'line',
        'catalog_item_id',
        'item_presentation_id',
        'description',
        'quantity',
        'list_price',
        'unit_price',
        'discount_percent',
        'effective_discount_percent',
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
            'quantity' => 'decimal:4',
            'list_price' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'effective_discount_percent' => 'decimal:2',
            'tax_type' => TaxType::class,
            'line_subtotal' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    /**
     * @return BelongsTo<ItemPresentation, $this>
     */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ItemPresentation::class, 'item_presentation_id');
    }
}
