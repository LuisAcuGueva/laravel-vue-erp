<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property string|null $parent_id
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property int $depth
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category|null $parent
 * @property-read Collection<int, Category> $children
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'categories';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'depth' => 0,
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
        'parent_id',
        'code',
        'name',
        'description',
        'depth',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => $value !== null && trim($value) !== '' ? strtoupper(trim($value)) : null,
        );
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Ruta legible desde la raíz (ej. "Tecnología › Cómputo › Laptops").
     * Espera cargados `parent.parent` para no consultar por fila.
     */
    public function path(): string
    {
        $names = [$this->name];
        $node = $this;

        while ($node->parent_id !== null && ($node = $node->parent) !== null) {
            array_unshift($names, $node->name);
        }

        return implode(' › ', $names);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Devuelve los IDs de los ancestros directos e indirectos, desde el padre hacia arriba.
     *
     * @return list<string>
     */
    public function ancestorIds(): array
    {
        $ancestors = [];
        $currentParentId = $this->parent_id;

        while ($currentParentId !== null && count($ancestors) < 10) {
            $ancestors[] = $currentParentId;
            $parent = self::find($currentParentId);
            $currentParentId = $parent?->parent_id;
        }

        return $ancestors;
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
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
                ->orWhere('code', $operator, $like)
                ->orWhere('description', $operator, $like);
        });
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }
}
