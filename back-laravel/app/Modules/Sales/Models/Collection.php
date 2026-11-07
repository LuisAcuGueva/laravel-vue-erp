<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Sales\Enums\CollectionStatus;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Cobro a un cliente aplicado a una o varias cuotas por cobrar.
 *
 * @property string $id
 * @property string $number
 * @property string $branch_id
 * @property string $client_id
 * @property Carbon $payment_date
 * @property string $currency_id
 * @property string $payment_method_id
 * @property string|null $cash_session_id
 * @property string|null $bank_account_id
 * @property string $amount
 * @property string|null $reference
 * @property string|null $notes
 * @property CollectionStatus $status
 * @property string|null $created_by
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 * @property-read Client $client
 * @property-read Currency $currency
 * @property-read PaymentMethod $paymentMethod
 * @property-read CashSession|null $cashSession
 * @property-read BankAccount|null $bankAccount
 * @property-read User|null $creator
 * @property-read User|null $canceller
 * @property-read EloquentCollection<int, CollectionAllocation> $allocations
 * @property-read EloquentCollection<int, CashMovement> $cashMovements
 * @property-read EloquentCollection<int, BankMovement> $bankMovements
 */
class Collection extends Model
{
    use HasUuids, LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'collections';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'completed',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'branch_id',
        'client_id',
        'payment_date',
        'currency_id',
        'payment_method_id',
        'cash_session_id',
        'bank_account_id',
        'amount',
        'reference',
        'notes',
        'status',
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
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'status' => CollectionStatus::class,
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'client_id', 'amount', 'status', 'cancel_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
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
     * @return HasMany<CollectionAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CollectionAllocation::class, 'collection_id');
    }

    /**
     * @return MorphMany<CashMovement, $this>
     */
    public function cashMovements(): MorphMany
    {
        return $this->morphMany(CashMovement::class, 'source');
    }

    /**
     * @return MorphMany<BankMovement, $this>
     */
    public function bankMovements(): MorphMany
    {
        return $this->morphMany(BankMovement::class, 'source');
    }

    public function getCollectionDateAttribute(): Carbon
    {
        return $this->payment_date;
    }

    public function getTotalAmountAttribute(): string
    {
        return (string) $this->amount;
    }
}
