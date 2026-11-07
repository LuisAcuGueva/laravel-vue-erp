<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $unit_category_id
 * @property string $name
 * @property string $symbol
 * @property bool $is_base
 * @property string $conversion_factor
 * @property bool $allow_decimals
 * @property string|null $sunat_code
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read UnitCategory $category
 */
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'units';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_base' => false,
        'conversion_factor' => '1.000000',
        'allow_decimals' => true,
        'is_active' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'unit_category_id',
        'name',
        'symbol',
        'is_base',
        'conversion_factor',
        'allow_decimals',
        'sunat_code',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'conversion_factor' => 'decimal:6',
            'allow_decimals' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function sunatCode(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => $value !== null && trim($value) !== '' ? strtoupper(trim($value)) : null,
        );
    }

    /**
     * @return BelongsTo<UnitCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(UnitCategory::class, 'unit_category_id');
    }

    /**
     * @param  Builder<Unit>  $query
     * @return Builder<Unit>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $query->where(function (Builder $inner) use ($like, $operator) {
            $inner->where('name', $operator, $like)
                ->orWhere('symbol', $operator, $like)
                ->orWhere('sunat_code', $operator, $like);
        });
    }

    /**
     * @param  Builder<Unit>  $query
     * @return Builder<Unit>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): UnitFactory
    {
        return UnitFactory::new();
    }
}
