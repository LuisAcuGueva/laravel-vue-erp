<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\PriceType;
use Database\Factories\ItemPriceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $item_presentation_id
 * @property PriceType $price_type
 * @property string $price
 * @property string $min_quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ItemPresentation $presentation
 */
class ItemPrice extends Model
{
    /** @use HasFactory<ItemPriceFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'item_prices';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'min_quantity' => 1,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_presentation_id',
        'price_type',
        'price',
        'min_quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_type' => PriceType::class,
            'price' => 'decimal:4',
            'min_quantity' => 'decimal:4',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['price', 'min_quantity'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<ItemPresentation, $this>
     */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ItemPresentation::class, 'item_presentation_id');
    }

    protected static function newFactory(): ItemPriceFactory
    {
        return ItemPriceFactory::new();
    }
}
