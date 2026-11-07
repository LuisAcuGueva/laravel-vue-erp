<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property string|null $sunat_code
 * @property int $decimals
 * @property bool $is_base
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'currencies';

    protected static ?self $cachedBase = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'decimals' => 2,
        'is_base' => false,
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'sunat_code',
        'decimals',
        'is_base',
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
            'decimals' => 'integer',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ExchangeRate, $this>
     */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'currency_id');
    }

    /**
     * @return HasMany<BankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'currency_id');
    }

    public static function base(): ?self
    {
        return static::$cachedBase ??= static::where('is_base', true)->first();
    }

    public static function clearBaseCache(): void
    {
        static::$cachedBase = null;
    }

    /**
     * @param  Builder<Currency>  $query
     * @return Builder<Currency>
     */
    public function scopeBase(Builder $query): Builder
    {
        return $query->where('is_base', true);
    }

    /**
     * @param  Builder<Currency>  $query
     * @return Builder<Currency>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Currency>  $query
     * @return Builder<Currency>
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
            $inner->where('code', $operator, $like)
                ->orWhere('name', $operator, $like)
                ->orWhere('symbol', $operator, $like);
        });
    }

    protected static function newFactory(): CurrencyFactory
    {
        return CurrencyFactory::new();
    }
}
