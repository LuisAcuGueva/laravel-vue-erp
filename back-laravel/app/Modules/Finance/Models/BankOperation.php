<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\BankOperationStatus;
use App\Modules\Finance\Enums\BankOperationType;
use App\Modules\Users\Models\User;
use Database\Factories\BankOperationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
 * @property BankOperationType $type
 * @property BankOperationStatus $status
 * @property string $bank_account_id
 * @property string|null $target_bank_account_id
 * @property string|null $cash_session_id
 * @property string $amount
 * @property string|null $target_amount
 * @property string|null $exchange_rate
 * @property string|null $operation_number
 * @property Carbon $operation_date
 * @property string|null $notes
 * @property string|null $voucher_path
 * @property string|null $created_by
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read BankAccount $bankAccount
 * @property-read BankAccount|null $targetBankAccount
 * @property-read CashSession|null $cashSession
 * @property-read User|null $creator
 * @property-read User|null $canceller
 * @property-read Collection<int, BankMovement> $bankMovements
 * @property-read Collection<int, CashMovement> $cashMovements
 */
class BankOperation extends Model
{
    /** @use HasFactory<BankOperationFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'bank_operations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'type',
        'status',
        'bank_account_id',
        'target_bank_account_id',
        'cash_session_id',
        'amount',
        'target_amount',
        'exchange_rate',
        'operation_number',
        'operation_date',
        'notes',
        'voucher_path',
        'created_by',
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
            'type' => BankOperationType::class,
            'status' => BankOperationStatus::class,
            'amount' => 'decimal:2',
            'target_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'operation_date' => 'date',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'cancelled_by',
                'cancelled_at',
                'cancel_reason',
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
     * @return BelongsTo<BankAccount, $this>
     */
    public function targetBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'target_bank_account_id');
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
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
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return MorphMany<BankMovement, $this>
     */
    public function bankMovements(): MorphMany
    {
        return $this->morphMany(BankMovement::class, 'source');
    }

    /**
     * @return MorphMany<CashMovement, $this>
     */
    public function cashMovements(): MorphMany
    {
        return $this->morphMany(CashMovement::class, 'source');
    }

    protected static function newFactory(): BankOperationFactory
    {
        return BankOperationFactory::new();
    }
}
