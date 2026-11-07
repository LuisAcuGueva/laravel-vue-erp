<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Finance\Models\Currency;
use App\Modules\Inventory\Models\ItemPresentation;
use Database\Factories\ProviderPriceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $provider_id
 * @property string $item_presentation_id
 * @property string $currency_id
 * @property string $price
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 * @property string|null $notes
 * @property string|null $last_purchase_price
 * @property Carbon|null $last_purchase_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Provider $provider
 * @property-read ItemPresentation $presentation
 * @property-read Currency $currency
 */
class ProviderPrice extends Model
{
    /** @use HasFactory<ProviderPriceFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'provider_prices';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'item_presentation_id',
        'currency_id',
        'price',
        'valid_from',
        'valid_until',
        'notes',
        'last_purchase_price',
        'last_purchase_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'last_purchase_price' => 'decimal:4',
            'last_purchase_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    /**
     * @return BelongsTo<ItemPresentation, $this>
     */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ItemPresentation::class, 'item_presentation_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * Precios cuya vigencia incluye la fecha dada (`valid_until` nulo = vigente indefinidamente).
     *
     * @param  Builder<ProviderPrice>  $query
     * @return Builder<ProviderPrice>
     */
    public function scopeValidOn(Builder $query, Carbon $date): Builder
    {
        $day = $date->toDateString();

        return $query->whereDate('valid_from', '<=', $day)
            ->where(function (Builder $inner) use ($day) {
                $inner->whereNull('valid_until')->orWhereDate('valid_until', '>=', $day);
            });
    }

    protected static function newFactory(): ProviderPriceFactory
    {
        return ProviderPriceFactory::new();
    }
}
