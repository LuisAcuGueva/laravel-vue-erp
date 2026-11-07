<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Area;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\Priority;
use App\Modules\Purchases\Enums\PurchaseRequestStatus;
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
 * @property string $area_id
 * @property string $warehouse_id
 * @property string $requested_by
 * @property Carbon $required_date
 * @property Priority $priority
 * @property PurchaseRequestStatus $status
 * @property string|null $notes
 * @property string|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 * @property-read Area $area
 * @property-read Warehouse $warehouse
 * @property-read User $requester
 * @property-read User|null $approver
 * @property-read Collection<int, PurchaseRequestItem> $items
 */
class PurchaseRequest extends Model
{
    use HasUuids, LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'purchase_requests';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 'normal',
        'status' => 'draft',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'branch_id',
        'area_id',
        'warehouse_id',
        'requested_by',
        'required_date',
        'priority',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'rejection_reason', 'cancel_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_date' => 'date',
            'priority' => Priority::class,
            'status' => PurchaseRequestStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PurchaseRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id')->orderBy('line');
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseRequestStatus::Draft;
    }
}
