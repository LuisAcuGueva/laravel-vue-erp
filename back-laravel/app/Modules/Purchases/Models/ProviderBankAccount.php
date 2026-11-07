<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\Currency;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $provider_id
 * @property string $bank_id
 * @property string $currency_id
 * @property string $account_number
 * @property string|null $cci
 * @property bool $is_detraction_account
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Provider $provider
 * @property-read Bank $bank
 * @property-read Currency $currency
 */
class ProviderBankAccount extends Model
{
    use HasUuids, LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'provider_bank_accounts';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_detraction_account' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'bank_id',
        'currency_id',
        'account_number',
        'cci',
        'is_detraction_account',
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
            'is_detraction_account' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
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
}
