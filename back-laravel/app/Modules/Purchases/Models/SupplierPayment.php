<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\SupplierPaymentStatus;
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
 * Pago a un proveedor aplicado a una o varias cuotas por pagar.
 *
 * @property string $id
 * @property string $number
 * @property string $branch_id
 * @property string|null $provider_id
 * @property string|null $counterparty_name
 * @property Carbon $payment_date
 * @property string $currency_id
 * @property string $exchange_rate
 * @property string $payment_method_id
 * @property string|null $cash_session_id
 * @property string|null $bank_account_id
 * @property string $amount
 * @property string|null $reference
 * @property string|null $notes
 * @property SupplierPaymentStatus $status
 * @property string|null $created_by
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 * @property-read Provider|null $provider
 * @property-read Currency $currency
 * @property-read PaymentMethod $paymentMethod
 * @property-read CashSession|null $cashSession
 * @property-read BankAccount|null $bankAccount
 * @property-read User|null $creator
 * @property-read User|null $canceller
 * @property-read Collection<int, SupplierPaymentAllocation> $allocations
 * @property-read Collection<int, CashMovement> $cashMovements
 * @property-read Collection<int, BankMovement> $bankMovements
 */
class SupplierPayment extends Model
{
    use HasUuids, LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'supplier_payments';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'completed',
        'exchange_rate' => 1,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'branch_id',
        'provider_id',
        'counterparty_name',
        'payment_date',
        'currency_id',
        'exchange_rate',
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
            'exchange_rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'status' => SupplierPaymentStatus::class,
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount', 'cancel_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function counterpartyDisplayName(): ?string
    {
        return $this->provider !== null ? $this->provider->business_name : $this->counterparty_name;
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
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
     * @return HasMany<SupplierPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'supplier_payment_id');
    }

    /**
     * @return MorphMany<CashMovement, $this>
     */
    public function cashMovements(): MorphMany
    {
        return $this->morphMany(CashMovement::class, 'source', 'source_type', 'source_id');
    }

    /**
     * @return MorphMany<BankMovement, $this>
     */
    public function bankMovements(): MorphMany
    {
        return $this->morphMany(BankMovement::class, 'source', 'source_type', 'source_id');
    }
}
