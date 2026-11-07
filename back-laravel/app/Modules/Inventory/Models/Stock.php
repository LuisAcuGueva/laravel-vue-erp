<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $warehouse_id
 * @property string $catalog_item_id
 * @property float $quantity
 * @property float $avg_cost
 * @property float $min_stock
 * @property float|null $max_stock
 * @property Carbon|null $last_movement_at
 * @property-read float $total_value
 * @property-read bool $is_below_min
 * @property-read Warehouse $warehouse
 * @property-read CatalogItem $catalogItem
 */
class Stock extends Model
{
    use HasUuids;

    protected $table = 'stocks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'warehouse_id',
        'catalog_item_id',
        'min_stock',
        'max_stock',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'float',
        'avg_cost' => 'float',
        'min_stock' => 'float',
        'max_stock' => 'float',
        'last_movement_at' => 'datetime',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'total_value',
        'is_below_min',
    ];

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    /**
     * @return Attribute<float, never>
     */
    protected function totalValue(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) bcmul((string) $this->quantity, (string) $this->avg_cost, 4)
        );
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isBelowMin(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->quantity < (float) $this->min_stock
        );
    }
}
