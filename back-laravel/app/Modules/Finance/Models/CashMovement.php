<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $cash_session_id
 * @property string $box_id
 * @property string $branch_id
 * @property MoneyDirection $direction
 * @property string $concept_id
 * @property string $payment_method_id
 * @property bool $affects_cash
 * @property string $amount
 * @property string|null $reference
 * @property string|null $description
 * @property string|null $counterparty_name
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string|null $source_number
 * @property string|null $reverses_id
 * @property bool $is_manual
 * @property string|null $created_by
 * @property Carbon $moved_at
 * @property Carbon $created_at
 * @property-read CashSession|null $session
 * @property-read Box|null $box
 * @property-read Branch|null $branch
 * @property-read FinanceConcept|null $concept
 * @property-read PaymentMethod|null $paymentMethod
 * @property-read Model|null $source
 * @property-read CashMovement|null $reverses
 * @property-read CashMovement|null $reversedBy
 * @property-read User|null $creator
 */
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'cash_movements';

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
            'direction' => MoneyDirection::class,
            'affects_cash' => 'boolean',
            'amount' => 'decimal:2',
            'is_manual' => 'boolean',
            'moved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Un movimiento de caja es inmutable.');
        });

        static::deleting(function () {
            throw new LogicException('Un movimiento de caja no se puede eliminar.');
        });
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
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
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    /**
     * @return BelongsTo<CashMovement, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * @return HasOne<CashMovement, $this>
     */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
