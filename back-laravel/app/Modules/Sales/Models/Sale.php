<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Audit\Concerns\LogsSystemActivity;
use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Sales\Enums\EInvoiceStatus;
use App\Modules\Sales\Enums\PaymentCondition;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Enums\SaleStatus;
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
 * @property string $id
 * @property string $branch_id
 * @property string $warehouse_id
 * @property string|null $cash_session_id
 * @property string $document_series_id
 * @property SalesDocumentType $document_type
 * @property string $series
 * @property int $number
 * @property string $full_number
 * @property Carbon $issue_date
 * @property Carbon $issued_at
 * @property Carbon|null $due_date
 * @property string $client_id
 * @property string|null $client_document_type
 * @property string|null $client_document_number
 * @property string $client_name
 * @property string|null $client_address
 * @property string|null $seller_id
 * @property string|null $quotation_id
 * @property string $currency_id
 * @property string $exchange_rate
 * @property PriceType|string $price_type
 * @property bool $prices_include_tax
 * @property string $tax_rate
 * @property string $global_discount_percent
 * @property string $taxable_base
 * @property string $exempt_base
 * @property string $unaffected_base
 * @property string $discount_total
 * @property string $tax_total
 * @property string $total
 * @property string $total_base_currency
 * @property string $cost_total
 * @property PaymentCondition $payment_condition
 * @property string $paid_amount
 * @property string $balance
 * @property SaleStatus $status
 * @property EInvoiceStatus $einvoice_status
 * @property int $einvoice_attempts
 * @property array<string, mixed>|null $einvoice_response
 * @property Carbon|null $einvoice_sent_at
 * @property string|null $discount_override_by
 * @property string|null $notes
 * @property string|null $created_by
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 * @property-read Warehouse $warehouse
 * @property-read CashSession|null $cashSession
 * @property-read DocumentSeries $series
 * @property-read Client $client
 * @property-read Employee|null $seller
 * @property-read Quotation|null $quotation
 * @property-read Currency $currency
 * @property-read User|null $creator
 * @property-read User|null $canceller
 * @property-read Collection<int, SaleItem> $items
 * @property-read Collection<int, SalePayment> $payments
 * @property-read Collection<int, ReceivableInstallment> $installments
 * @property-read Collection<int, CollectionAllocation> $collectionAllocations
 * @property-read Collection<int, CreditNote> $creditNotes
 * @property-read Collection<int, KardexMovement> $kardexMovements
 */
class Sale extends Model
{
    use HasUuids, LogsActivity, LogsSystemActivity;

    protected $table = 'sales';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'cash_session_id',
        'document_series_id',
        'document_type',
        'series',
        'number',
        'full_number',
        'issue_date',
        'issued_at',
        'due_date',
        'client_id',
        'client_document_type',
        'client_document_number',
        'client_name',
        'client_address',
        'seller_id',
        'quotation_id',
        'currency_id',
        'exchange_rate',
        'price_type',
        'prices_include_tax',
        'tax_rate',
        'global_discount_percent',
        'taxable_base',
        'exempt_base',
        'unaffected_base',
        'discount_total',
        'tax_total',
        'total',
        'total_base_currency',
        'cost_total',
        'payment_condition',
        'paid_amount',
        'balance',
        'status',
        'einvoice_status',
        'einvoice_attempts',
        'einvoice_response',
        'einvoice_sent_at',
        'discount_override_by',
        'notes',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'exchange_rate' => '1.0000',
        'price_type' => 'retail',
        'prices_include_tax' => true,
        'global_discount_percent' => '0.00',
        'taxable_base' => '0.00',
        'exempt_base' => '0.00',
        'unaffected_base' => '0.00',
        'discount_total' => '0.00',
        'tax_total' => '0.00',
        'cost_total' => '0.0000',
        'paid_amount' => '0.00',
        'balance' => '0.00',
        'status' => 'issued',
        'einvoice_status' => 'not_applicable',
        'einvoice_attempts' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => SalesDocumentType::class,
            'number' => 'integer',
            'issue_date' => 'date',
            'issued_at' => 'datetime',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:4',
            'price_type' => PriceType::class,
            'prices_include_tax' => 'boolean',
            'tax_rate' => 'decimal:2',
            'global_discount_percent' => 'decimal:2',
            'taxable_base' => 'decimal:2',
            'exempt_base' => 'decimal:2',
            'unaffected_base' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'total_base_currency' => 'decimal:2',
            'cost_total' => 'decimal:4',
            'payment_condition' => PaymentCondition::class,
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'status' => SaleStatus::class,
            'einvoice_status' => EInvoiceStatus::class,
            'einvoice_attempts' => 'integer',
            'einvoice_response' => 'array',
            'einvoice_sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'einvoice_status'])
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
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    /**
     * @return BelongsTo<DocumentSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class, 'document_series_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'seller_id');
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
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
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function discountOverrideUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_override_by');
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id')->orderBy('line');
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'sale_id');
    }

    /**
     * @return HasMany<ReceivableInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(ReceivableInstallment::class, 'sale_id')->orderBy('number');
    }

    /**
     * @return HasMany<CollectionAllocation, $this>
     */
    public function collectionAllocations(): HasMany
    {
        return $this->hasMany(CollectionAllocation::class, 'sale_id');
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class, 'sale_id');
    }

    /**
     * @return MorphMany<KardexMovement, $this>
     */
    public function kardexMovements(): MorphMany
    {
        return $this->morphMany(KardexMovement::class, 'source');
    }
}
