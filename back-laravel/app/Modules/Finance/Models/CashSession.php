<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\CashReviewStatus;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $number
 * @property string $box_id
 * @property string $branch_id
 * @property CashSessionStatus $status
 * @property string $opened_by
 * @property Carbon $opened_at
 * @property string $opening_amount
 * @property string|null $opening_notes
 * @property string|null $closed_by
 * @property Carbon|null $closed_at
 * @property string|null $expected_cash
 * @property string|null $counted_cash
 * @property string|null $difference
 * @property string|null $closing_notes
 * @property CashReviewStatus|null $review_status
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Box|null $box
 * @property-read Branch|null $branch
 * @property-read User|null $opener
 * @property-read User|null $closer
 * @property-read User|null $reviewer
 * @property-read Collection<int, CashCountDetail> $countDetails
 * @property-read Collection<int, CashMovement> $movements
 */
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'cash_sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'box_id',
        'branch_id',
        'status',
        'opened_by',
        'opened_at',
        'opening_amount',
        'opening_notes',
        'closed_by',
        'closed_at',
        'expected_cash',
        'counted_cash',
        'difference',
        'closing_notes',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
        'opening_amount' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'review_status' => CashReviewStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Box, $this>
     */
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class, 'box_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<CashCountDetail, $this>
     */
    public function countDetails(): HasMany
    {
        return $this->hasMany(CashCountDetail::class, 'cash_session_id');
    }

    /**
     * @return HasMany<CashMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cash_session_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'expected_cash',
                'counted_cash',
                'difference',
                'review_status',
                'reviewed_by',
                'reviewed_at',
                'review_notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function newFactory(): CashSessionFactory
    {
        return CashSessionFactory::new();
    }
}
