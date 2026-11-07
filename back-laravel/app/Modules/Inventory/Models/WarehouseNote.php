<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\WarehouseNoteStatus;
use App\Modules\Inventory\Enums\WarehouseNoteType;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $number
 * @property WarehouseNoteType $type
 * @property WarehouseNoteStatus $status
 * @property string $branch_id
 * @property string $warehouse_id
 * @property string|null $target_warehouse_id
 * @property string $reason_id
 * @property string|null $inventory_count_id
 * @property Carbon $document_date
 * @property string|null $reference_document
 * @property string|null $observation
 * @property float $total_cost
 * @property string|null $created_by
 * @property string|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 * @property-read Warehouse $warehouse
 * @property-read Warehouse|null $targetWarehouse
 * @property-read WarehouseReason $reason
 * @property-read InventoryCount|null $inventoryCount
 * @property-read User|null $creator
 * @property-read User|null $approver
 * @property-read User|null $canceller
 * @property-read Collection<int, WarehouseNoteItem> $items
 * @property-read Collection<int, KardexMovement> $kardexMovements
 */
class WarehouseNote extends Model
{
    use HasUuids, LogsActivity;

    protected $table = 'warehouse_notes';

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
            'type' => WarehouseNoteType::class,
            'status' => WarehouseNoteStatus::class,
            'document_date' => 'date',
            'total_cost' => 'float',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'observation', 'cancel_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function isDraft(): bool
    {
        return $this->status === WarehouseNoteStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->status === WarehouseNoteStatus::Approved;
    }

    public function isCancelled(): bool
    {
        return $this->status === WarehouseNoteStatus::Cancelled;
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
     * @return BelongsTo<Warehouse, $this>
     */
    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    /**
     * @return BelongsTo<WarehouseReason, $this>
     */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(WarehouseReason::class, 'reason_id');
    }

    /**
     * @return BelongsTo<InventoryCount, $this>
     */
    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class, 'inventory_count_id');
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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<WarehouseNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WarehouseNoteItem::class, 'warehouse_note_id')->orderBy('line');
    }

    /**
     * @return MorphMany<KardexMovement, $this>
     */
    public function kardexMovements(): MorphMany
    {
        return $this->morphMany(KardexMovement::class, 'source');
    }
}
