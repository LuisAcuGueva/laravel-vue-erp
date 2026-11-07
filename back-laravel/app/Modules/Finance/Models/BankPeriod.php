<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Users\Models\User;
use Database\Factories\BankPeriodFactory;
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
 * @property string $bank_account_id
 * @property int $number
 * @property BankPeriodStatus $status
 * @property Carbon $opened_at
 * @property string $opening_balance
 * @property Carbon|null $closed_at
 * @property string|null $closed_by
 * @property string|null $expected_balance
 * @property string|null $statement_balance
 * @property string|null $difference
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read BankAccount $bankAccount
 * @property-read User|null $closedByUser
 * @property-read Collection<int, BankMovement> $movements
 */
class BankPeriod extends Model
{
    /** @use HasFactory<BankPeriodFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'bank_periods';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bank_account_id',
        'number',
        'status',
        'opened_at',
        'opening_balance',
        'closed_at',
        'closed_by',
        'expected_balance',
        'statement_balance',
        'difference',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => BankPeriodStatus::class,
            'opened_at' => 'date',
            'closed_at' => 'date',
            'opening_balance' => 'decimal:2',
            'expected_balance' => 'decimal:2',
            'statement_balance' => 'decimal:2',
            'difference' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'closed_at',
                'closed_by',
                'expected_balance',
                'statement_balance',
                'difference',
                'notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<BankMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(BankMovement::class, 'bank_period_id');
    }

    protected static function newFactory(): BankPeriodFactory
    {
        return BankPeriodFactory::new();
    }
}
