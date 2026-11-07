<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Finance\Models\Currency;
use App\Modules\Purchases\Enums\SupplierInvoiceStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cuota por pagar de una factura de proveedor.
 *
 * @property string $id
 * @property string $supplier_invoice_id
 * @property string|null $provider_id
 * @property string $currency_id
 * @property int $number
 * @property Carbon $due_date
 * @property string $amount
 * @property string $paid_amount
 * @property string $balance
 * @property SupplierInvoiceStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read bool $is_overdue
 * @property-read SupplierInvoice $invoice
 * @property-read Provider|null $provider
 * @property-read Currency $currency
 * @property-read Collection<int, SupplierPaymentAllocation> $allocations
 */
class PayableInstallment extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'payable_installments';

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
        'supplier_invoice_id',
        'provider_id',
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
            'status' => SupplierInvoiceStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Vencida: fecha de vencimiento anterior a hoy y saldo pendiente.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== SupplierInvoiceStatus::Cancelled
            && (float) $this->balance > 0
            && $this->due_date->lt(today());
    }

    /**
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
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
     * @return HasMany<SupplierPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'payable_installment_id');
    }
}
