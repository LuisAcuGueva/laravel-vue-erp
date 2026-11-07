<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $inventory_count_id
 * @property string $catalog_item_id
 * @property float $system_quantity
 * @property float|null $counted_quantity
 * @property float|null $closing_system_quantity
 * @property float|null $difference
 * @property float $unit_cost
 * @property float|null $difference_value
 * @property string|null $counted_by
 * @property Carbon|null $counted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read InventoryCount $inventoryCount
 * @property-read CatalogItem $catalogItem
 * @property-read User|null $counter
 */
class InventoryCountItem extends Model
{
    use HasUuids;

    protected $table = 'inventory_count_items';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_quantity' => 'float',
            'counted_quantity' => 'float',
            'closing_system_quantity' => 'float',
            'difference' => 'float',
            'unit_cost' => 'float',
            'difference_value' => 'float',
            'counted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InventoryCount, $this>
     */
    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class, 'inventory_count_id');
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
