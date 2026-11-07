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
 * @property string $sale_id
 * @property int $line
 * @property string|null $catalog_item_id
 * @property string|null $item_presentation_id
 * @property string $description
 * @property string|null $unit_sunat_code
 * @property string $quantity
 * @property string|null $base_quantity
 * @property string|null $list_price
 * @property string $unit_price
 * @property string $discount_percent
 * @property string $effective_discount_percent
 * @property TaxType $tax_type
 * @property string $line_subtotal
 * @property string $line_tax
 * @property string $line_total
 * @property string $unit_cost
 * @property string $cost_total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Sale $sale
 * @property-read CatalogItem|null $catalogItem
 * @property-read ItemPresentation|null $itemPresentation
 */
class SaleItem extends Model
{
    use HasUuids;

    protected $table = 'sale_items';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_id',
        'line',
        'catalog_item_id',
        'item_presentation_id',
        'description',
        'unit_sunat_code',
        'quantity',
        'base_quantity',
        'list_price',
        'unit_price',
        'discount_percent',
        'effective_discount_percent',
        'tax_type',
        'line_subtotal',
        'line_tax',
        'line_total',
        'unit_cost',
        'cost_total',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'discount_percent' => '0.00',
        'effective_discount_percent' => '0.00',
        'tax_type' => 'taxable',
        'unit_cost' => '0.000000',
        'cost_total' => '0.0000',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'list_price' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'effective_discount_percent' => 'decimal:2',
            'tax_type' => TaxType::class,
            'line_subtotal' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_cost' => 'decimal:6',
            'cost_total' => 'decimal:4',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
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
