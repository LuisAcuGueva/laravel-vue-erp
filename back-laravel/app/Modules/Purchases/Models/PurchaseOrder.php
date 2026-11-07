<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Finance\Models\Currency;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\PaymentCondition;
use App\Modules\Purchases\Enums\PurchaseOrderStatus;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $number
 * @property string $branch_id
 * @property string $provider_id
 * @property string $warehouse_id
 * @property Carbon $order_date
 * @property Carbon|null $expected_date
 * @property string $currency_id
 * @property string $exchange_rate
 * @property PaymentCondition $payment_condition
 * @property int $credit_days
 * @property bool $prices_include_tax
 * @property string $tax_rate
 * @property string $taxable_base
 * @property string $exempt_base
 * @property string $unaffected_base
 * @property string $discount_total
 * @property string $tax_total
 * @property string $total
 * @property string $total_base_currency
 * @property PurchaseOrderStatus $status
 * @property string|null $notes
 * @property string|null $terms
 * @property string|null $created_by
 * @property string|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property string|null $closed_by
 * @property Carbon|null $closed_at
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 * @property-read Provider $provider
 * @property-read Warehouse $warehouse
 * @property-read Currency $currency
 * @property-read User|null $creator
 * @property-read User|null $approver
 * @property-read Collection<int, PurchaseOrderItem> $items
 * @property-read Collection<int, PurchaseReceipt> $receipts
 */
class PurchaseOrder extends Model
{
    use HasUuids, LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'purchase_orders';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'exchange_rate' => 1,
        'credit_days' => 0,
        'prices_include_tax' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'branch_id',
        'provider_id',
        'warehouse_id',
        'order_date',
        'expected_date',
        'currency_id',
        'exchange_rate',
        'payment_condition',
        'credit_days',
        'prices_include_tax',
        'tax_rate',
        'taxable_base',
        'exempt_base',
        'unaffected_base',
        'discount_total',
        'tax_total',
        'total',
        'total_base_currency',
        'status',
        'notes',
        'terms',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'closed_by',
        'closed_at',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total', 'total_base_currency', 'rejection_reason', 'cancel_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'exchange_rate' => 'decimal:4',
            'payment_condition' => PaymentCondition::class,
            'credit_days' => 'integer',
            'prices_include_tax' => 'boolean',
            'tax_rate' => 'decimal:2',
            'taxable_base' => 'decimal:2',
            'exempt_base' => 'decimal:2',
            'unaffected_base' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'total_base_currency' => 'decimal:2',
            'status' => PurchaseOrderStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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
        return $this->belongsTo(Provider::class, 'provider_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id')->orderBy('line');
    }

    /**
     * @return HasMany<PurchaseReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class, 'purchase_order_id')->orderByDesc('receipt_date');
    }
}
