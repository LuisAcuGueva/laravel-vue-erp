<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Models\Currency;
use App\Modules\Sales\Enums\InstallmentStatus;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cuota por cobrar de una venta al crédito.
 *
 * @property string $id
 * @property string $sale_id
 * @property string $client_id
 * @property string $currency_id
 * @property int $number
 * @property Carbon $due_date
 * @property string $amount
 * @property string $paid_amount
 * @property string $balance
 * @property InstallmentStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read bool $is_overdue
 * @property-read int $days_overdue
 * @property-read Sale $sale
 * @property-read Client $client
 * @property-read Currency $currency
 * @property-read EloquentCollection<int, CollectionAllocation> $allocations
 */
class ReceivableInstallment extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'receivable_installments';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'paid_amount' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_id',
        'client_id',
        'currency_id',
        'number',
        'due_date',
        'amount',
        'paid_amount',
        'balance',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'status' => InstallmentStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Determina si la cuota está vencida (saldo > 0 y due_date anterior a hoy).
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== InstallmentStatus::Cancelled
            && (float) $this->balance > 0
            && $this->due_date->lt(today());
    }

    /**
     * Días de mora si la cuota está vencida.
     */
    public function getDaysOverdueAttribute(): int
    {
        if (! $this->is_overdue) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(today());
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
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
     * @return HasMany<CollectionAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CollectionAllocation::class, 'receivable_installment_id');
    }
}
