<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Database\Factories\ItemPresentationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $catalog_item_id
 * @property string $unit_id
 * @property string $name
 * @property string $factor
 * @property string|null $barcode
 * @property bool $is_base
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CatalogItem $item
 * @property-read Unit $unit
 * @property-read Collection<int, ItemPrice> $prices
 */
class ItemPresentation extends Model
{
    /** @use HasFactory<ItemPresentationFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'item_presentations';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'factor' => 1,
        'is_base' => false,
        'is_default' => false,
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'catalog_item_id',
        'unit_id',
        'name',
        'factor',
        'barcode',
        'is_base',
        'is_default',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor' => 'decimal:4',
            'is_base' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * @return HasMany<ItemPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ItemPrice::class, 'item_presentation_id');
    }

    /**
     * Convierte una cantidad de esta presentación a su equivalente en unidad base.
     */
    public function toBaseQuantity(float $qty): float
    {
        return round($qty * (float) $this->factor, 4);
    }

    protected static function newFactory(): ItemPresentationFactory
    {
        return ItemPresentationFactory::new();
    }
}
