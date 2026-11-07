<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\InventoryCountStatus;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $number
 * @property string $branch_id
 * @property string $warehouse_id
 * @property string|null $category_id
 * @property Carbon $count_date
 * @property InventoryCountStatus $status
 * @property string|null $notes
 * @property string|null $created_by
 * @property string|null $closed_by
 * @property Carbon|null $closed_at
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 * @property-read Warehouse $warehouse
 * @property-read Category|null $category
 * @property-read User|null $creator
 * @property-read User|null $closer
 * @property-read User|null $canceller
 * @property-read Collection<int, InventoryCountItem> $items
 * @property-read Collection<int, WarehouseNote> $notes
 */
class InventoryCount extends Model
{
    use HasUuids, LogsActivity;

    protected $table = 'inventory_counts';

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
            'count_date' => 'date',
            'status' => InventoryCountStatus::class,
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function isCounting(): bool
    {
        return $this->status === InventoryCountStatus::Counting;
    }

    public function isClosed(): bool
    {
        return $this->status === InventoryCountStatus::Closed;
    }

    public function isCancelled(): bool
    {
        return $this->status === InventoryCountStatus::Cancelled;
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<InventoryCountItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class, 'inventory_count_id');
    }

    /**
     * @return HasMany<WarehouseNote, $this>
     */
    public function warehouseNotes(): HasMany
    {
        return $this->hasMany(WarehouseNote::class, 'inventory_count_id');
    }
}
