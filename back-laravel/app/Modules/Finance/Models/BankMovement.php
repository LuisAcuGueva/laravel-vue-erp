<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Database\Factories\BankMovementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $bank_account_id
 * @property string $bank_period_id
 * @property string|null $branch_id
 * @property MoneyDirection $direction
 * @property string $concept_id
 * @property string|null $payment_method_id
 * @property string $amount
 * @property string $balance_after
 * @property string|null $operation_number
 * @property Carbon $movement_date
 * @property string|null $description
 * @property string|null $counterparty_name
 * @property string|null $voucher_path
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string|null $source_number
 * @property string|null $reverses_id
 * @property bool $is_manual
 * @property string|null $created_by
 * @property Carbon $created_at
 * @property-read BankAccount $bankAccount
 * @property-read BankPeriod $period
 * @property-read Branch|null $branch
 * @property-read FinanceConcept $concept
 * @property-read PaymentMethod|null $paymentMethod
 * @property-read User|null $creator
 * @property-read Model|null $source
 * @property-read BankMovement|null $reversedMovement
 * @property-read BankMovement|null $reversal
 */
class BankMovement extends Model
{
    /** @use HasFactory<BankMovementFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'bank_movements';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bank_account_id',
        'bank_period_id',
        'branch_id',
        'direction',
        'concept_id',
        'payment_method_id',
        'amount',
        'balance_after',
        'operation_number',
        'movement_date',
        'description',
        'counterparty_name',
        'voucher_path',
        'source_type',
        'source_id',
        'source_number',
        'reverses_id',
        'is_manual',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MoneyDirection::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'movement_date' => 'date',
            'is_manual' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * @return BelongsTo<BankPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(BankPeriod::class, 'bank_period_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<FinanceConcept, $this>
     */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(FinanceConcept::class, 'concept_id');
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<BankMovement, $this>
     */
    public function reversedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * @return HasOne<BankMovement, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    protected static function newFactory(): BankMovementFactory
    {
        return BankMovementFactory::new();
    }
}
