<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\WarehouseReasonType;
use Database\Factories\WarehouseReasonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property WarehouseReasonType $type
 * @property bool $requires_approval
 * @property bool $is_system
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class WarehouseReason extends Model
{
    /** @use HasFactory<WarehouseReasonFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'warehouse_reasons';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'requires_approval' => true,
        'is_system' => false,
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
        'code',
        'name',
        'type',
        'requires_approval',
        'is_system',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WarehouseReasonType::class,
            'requires_approval' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => strtoupper(trim($value)),
        );
    }

    /**
     * @param  Builder<WarehouseReason>  $query
     * @return Builder<WarehouseReason>
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
                ->orWhere('name', $operator, $like);
        });
    }

    /**
     * @param  Builder<WarehouseReason>  $query
     * @return Builder<WarehouseReason>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): WarehouseReasonFactory
    {
        return WarehouseReasonFactory::new();
    }
}
