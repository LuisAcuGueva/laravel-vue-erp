<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $warehouse_note_id
 * @property int $line
 * @property string $catalog_item_id
 * @property string $item_presentation_id
 * @property float $quantity
 * @property float $base_quantity
 * @property float|null $unit_cost
 * @property float|null $base_unit_cost
 * @property float $total_cost
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read WarehouseNote $warehouseNote
 * @property-read CatalogItem $catalogItem
 * @property-read ItemPresentation $presentation
 */
class WarehouseNoteItem extends Model
{
    use HasUuids;

    protected $table = 'warehouse_note_items';

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
            'line' => 'integer',
            'quantity' => 'float',
            'base_quantity' => 'float',
            'unit_cost' => 'float',
            'base_unit_cost' => 'float',
            'total_cost' => 'float',
        ];
    }

    /**
     * @return BelongsTo<WarehouseNote, $this>
     */
    public function warehouseNote(): BelongsTo
    {
        return $this->belongsTo(WarehouseNote::class, 'warehouse_note_id');
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
