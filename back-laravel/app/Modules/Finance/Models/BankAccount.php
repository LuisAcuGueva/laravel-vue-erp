<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\BankAccountType;
use App\Modules\Finance\Enums\BankPeriodStatus;
use App\Modules\Maintenance\Models\Branch;
use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $bank_id
 * @property string $currency_id
 * @property string|null $branch_id
 * @property string $alias
 * @property string $account_number
 * @property string|null $cci
 * @property BankAccountType $account_type
 * @property string|null $holder_name
 * @property string $balance
 * @property bool $is_active
 * @property string $display_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Bank|null $bank
 * @property-read Currency|null $currency
 * @property-read Branch|null $branch
 * @property-read BankPeriod|null $openPeriod
 * @property-read Collection<int, BankPeriod> $periods
 * @property-read Collection<int, BankMovement> $movements
 */
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'bank_accounts';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'balance' => 0,
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bank_id',
        'currency_id',
        'branch_id',
        'alias',
        'account_number',
        'cci',
        'account_type',
        'holder_name',
        'balance',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'display_name',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => BankAccountType::class,
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return HasMany<PaymentMethod, $this>
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'bank_account_id');
    }

    /**
     * @return HasMany<BankPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(BankPeriod::class, 'bank_account_id');
    }

    /**
     * @return HasOne<BankPeriod, $this>
     */
    public function openPeriod(): HasOne
    {
        return $this->hasOne(BankPeriod::class, 'bank_account_id')
            ->where('status', BankPeriodStatus::Open);
    }

    /**
     * @return HasMany<BankMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(BankMovement::class, 'bank_account_id');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $bankShort = $this->bank->short_name ?? '';
                $currencyCode = $this->currency->code ?? '';
                $lastDigits = substr($this->account_number ?? '', -4);

                return trim("{$bankShort} {$currencyCode} ···{$lastDigits}");
            }
        );
    }

    /**
     * @param  Builder<BankAccount>  $query
     * @return Builder<BankAccount>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<BankAccount>  $query
     * @return Builder<BankAccount>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $query->where(function (Builder $inner) use ($like, $operator) {
            $inner->where('alias', $operator, $like)
                ->orWhere('account_number', $operator, $like)
                ->orWhere('cci', $operator, $like)
                ->orWhere('holder_name', $operator, $like);
        });
    }

    protected static function newFactory(): BankAccountFactory
    {
        return BankAccountFactory::new();
    }
}
