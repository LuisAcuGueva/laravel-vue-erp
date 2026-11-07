<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\CashTransferStatus;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Database\Factories\CashTransferFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $number
 * @property CashTransferStatus $status
 * @property string $source_session_id
 * @property string $source_box_id
 * @property string $source_branch_id
 * @property string $target_box_id
 * @property string $target_branch_id
 * @property string|null $target_session_id
 * @property string $amount
 * @property string $reason
 * @property string|null $created_by
 * @property string|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CashSession $sourceSession
 * @property-read Box $sourceBox
 * @property-read Branch $sourceBranch
 * @property-read Box $targetBox
 * @property-read Branch $targetBranch
 * @property-read CashSession|null $targetSession
 * @property-read User|null $creator
 * @property-read User|null $approver
 * @property-read User|null $rejecter
 * @property-read User|null $canceller
 * @property-read Collection<int, CashMovement> $cashMovements
 */
class CashTransfer extends Model
{
    /** @use HasFactory<CashTransferFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'number',
        'status',
        'source_session_id',
        'source_box_id',
        'source_branch_id',
        'target_box_id',
        'target_branch_id',
        'target_session_id',
        'amount',
        'reason',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CashTransferStatus::class,
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'approved_by',
                'approved_at',
                'rejected_by',
                'rejected_at',
                'rejection_reason',
                'cancelled_by',
                'cancelled_at',
                'cancel_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function sourceSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'source_session_id');
    }

    /**
     * @return BelongsTo<Box, $this>
     */
    public function sourceBox(): BelongsTo
    {
        return $this->belongsTo(Box::class, 'source_box_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    /**
     * @return BelongsTo<Box, $this>
     */
    public function targetBox(): BelongsTo
    {
        return $this->belongsTo(Box::class, 'target_box_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function targetBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'target_branch_id');
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function targetSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'target_session_id');
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
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return MorphMany<CashMovement, $this>
     */
    public function cashMovements(): MorphMany
    {
        return $this->morphMany(CashMovement::class, 'source');
    }

    /**
     * @return Factory<CashTransfer>
     */
    protected static function newFactory(): Factory
    {
        return CashTransferFactory::new();
    }
}
