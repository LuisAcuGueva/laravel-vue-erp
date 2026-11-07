<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\CatalogItemType;
use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Services\CategoryService;
use Database\Factories\CatalogItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property CatalogItemType $type
 * @property string $category_id
 * @property string|null $brand_id
 * @property string $base_unit_id
 * @property string|null $description
 * @property TaxType $tax_type
 * @property bool $track_stock
 * @property bool $is_sellable
 * @property bool $is_purchasable
 * @property bool $is_active
 * @property string|null $last_purchase_cost
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category $category
 * @property-read Brand|null $brand
 * @property-read Unit $baseUnit
 * @property-read Collection<int, ItemPresentation> $presentations
 * @property-read ItemPresentation|null $defaultPresentation
 * @property-read Collection<int, ItemPrice> $prices
 */
class CatalogItem extends Model
{
    /** @use HasFactory<CatalogItemFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'catalog_items';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'product',
        'tax_type' => 'taxable',
        'track_stock' => true,
        'is_sellable' => true,
        'is_purchasable' => true,
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'type',
        'category_id',
        'brand_id',
        'base_unit_id',
        'description',
        'tax_type',
        'track_stock',
        'is_sellable',
        'is_purchasable',
        'is_active',
        'last_purchase_cost',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CatalogItemType::class,
            'tax_type' => TaxType::class,
            'track_stock' => 'boolean',
            'is_sellable' => 'boolean',
            'is_purchasable' => 'boolean',
            'is_active' => 'boolean',
            'last_purchase_cost' => 'decimal:6',
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    /**
     * @return HasMany<ItemPresentation, $this>
     */
    public function presentations(): HasMany
    {
        return $this->hasMany(ItemPresentation::class, 'catalog_item_id');
    }

    /**
     * @return HasOne<ItemPresentation, $this>
     */
    public function defaultPresentation(): HasOne
    {
        return $this->hasOne(ItemPresentation::class, 'catalog_item_id')
            ->where('is_default', true);
    }

    /**
     * @return HasManyThrough<ItemPrice, ItemPresentation, $this>
     */
    public function prices(): HasManyThrough
    {
        return $this->hasManyThrough(
            ItemPrice::class,
            ItemPresentation::class,
            'catalog_item_id',
            'item_presentation_id',
            'id',
            'id'
        );
    }

    /**
     * @param  Builder<CatalogItem>  $query
     * @return Builder<CatalogItem>
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
            $inner->where('code', $operator, $like)
                ->orWhere('name', $operator, $like)
                ->orWhereHas('presentations', function (Builder $p) use ($like, $operator) {
                    $p->where('barcode', $operator, $like);
                });
        });
    }

    /**
     * @param  Builder<CatalogItem>  $query
     * @return Builder<CatalogItem>
     */
    public function scopeOfType(Builder $query, CatalogItemType|string $type): Builder
    {
        $val = $type instanceof CatalogItemType ? $type->value : $type;

        return $query->where('type', $val);
    }

    /**
     * @param  Builder<CatalogItem>  $query
     * @return Builder<CatalogItem>
     */
    public function scopeInCategoryTree(Builder $query, string $categoryId): Builder
    {
        $categoryIds = CategoryService::descendantIds($categoryId);

        return $query->whereIn('category_id', $categoryIds);
    }

    /**
     * @param  Builder<CatalogItem>  $query
     * @return Builder<CatalogItem>
     */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('is_sellable', true);
    }

    /**
     * @param  Builder<CatalogItem>  $query
     * @return Builder<CatalogItem>
     */
    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('is_purchasable', true);
    }

    /**
     * @param  Builder<CatalogItem>  $query
     * @return Builder<CatalogItem>
     */
    public function scopeStockable(Builder $query): Builder
    {
        return $query->where('track_stock', true);
    }

    /**
     * @param  Builder<CatalogItem>  $query
     * @return Builder<CatalogItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): CatalogItemFactory
    {
        return CatalogItemFactory::new();
    }
}
