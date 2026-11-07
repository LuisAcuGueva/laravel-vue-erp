<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Database\Factories\UnitCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_packaging
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Unit> $units
 * @property-read Unit|null $baseUnit
 */
class UnitCategory extends Model
{
    /** @use HasFactory<UnitCategoryFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'unit_categories';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_packaging' => false,
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
        'name',
        'description',
        'is_packaging',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_packaging' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'unit_category_id');
    }

    /**
     * @return HasOne<Unit, $this>
     */
    public function baseUnit(): HasOne
    {
        return $this->hasOne(Unit::class, 'unit_category_id')->where('is_base', true);
    }

    /**
     * @param  Builder<UnitCategory>  $query
     * @return Builder<UnitCategory>
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
                ->orWhere('description', $operator, $like);
        });
    }

    /**
     * @param  Builder<UnitCategory>  $query
     * @return Builder<UnitCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): UnitCategoryFactory
    {
        return UnitCategoryFactory::new();
    }
}
