<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Finance\Models\Currency;
use App\Support\Identity\DocumentType;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Builder;
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
 * @property DocumentType $document_type
 * @property string $document_number
 * @property string $business_name
 * @property string|null $trade_name
 * @property string|null $address
 * @property string|null $ubigeo
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $default_currency_id
 * @property int $credit_days
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ProviderContact> $contacts
 * @property-read Collection<int, ProviderBankAccount> $bankAccounts
 * @property-read Collection<int, ProviderPrice> $prices
 * @property-read Currency|null $defaultCurrency
 * @property-read ProviderContact|null $primaryContact
 */
class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'providers';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'credit_days' => 0,
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_type',
        'document_number',
        'business_name',
        'trade_name',
        'address',
        'ubigeo',
        'email',
        'phone',
        'website',
        'default_currency_id',
        'credit_days',
        'notes',
        'is_active',
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
            'document_type' => DocumentType::class,
            'credit_days' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ProviderContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ProviderContact::class, 'provider_id')->orderByDesc('is_primary')->orderBy('name');
    }

    /**
     * @return HasMany<ProviderBankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(ProviderBankAccount::class, 'provider_id')->orderBy('created_at');
    }

    /**
     * @return HasMany<ProviderPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProviderPrice::class, 'provider_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }

    /**
     * @return HasOne<ProviderContact, $this>
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(ProviderContact::class, 'provider_id')->where('is_primary', true);
    }

    /**
     * @param  Builder<Provider>  $query
     * @return Builder<Provider>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $operator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $query->where(function (Builder $inner) use ($like, $operator) {
            $inner->where('document_number', $operator, $like)
                ->orWhere('business_name', $operator, $like)
                ->orWhere('trade_name', $operator, $like);
        });
    }

    /**
     * @param  Builder<Provider>  $query
     * @return Builder<Provider>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): ProviderFactory
    {
        return ProviderFactory::new();
    }
}
