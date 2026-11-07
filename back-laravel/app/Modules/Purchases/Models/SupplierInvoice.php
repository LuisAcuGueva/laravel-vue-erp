<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\FinanceConcept;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\SupplierDocumentType;
use App\Modules\Purchases\Enums\SupplierInvoiceKind;
use App\Modules\Purchases\Enums\SupplierInvoiceStatus;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Factura (o documento) de un proveedor: compra con OC o gasto directo.
 *
 * @property string $id
 * @property string $number
 * @property string $branch_id
 * @property SupplierInvoiceKind $kind
 * @property string|null $provider_id
 * @property string|null $counterparty_name
 * @property SupplierDocumentType $document_type
 * @property string|null $series
 * @property string $document_number
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property string $currency_id
 * @property string $exchange_rate
 * @property bool $prices_include_tax
 * @property string $tax_rate
 * @property string $taxable_base
 * @property string $exempt_base
 * @property string $unaffected_base
 * @property string $discount_total
 * @property string $tax_total
 * @property string $total
 * @property string $paid_amount
 * @property string $balance
 * @property SupplierInvoiceStatus $status
 * @property string|null $purchase_order_id
 * @property string|null $concept_id
 * @property string|null $notes
 * @property string|null $attachment_path
 * @property string|null $created_by
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $document_label
 * @property-read Branch $branch
 * @property-read Provider|null $provider
 * @property-read Currency $currency
 * @property-read PurchaseOrder|null $order
 * @property-read FinanceConcept|null $concept
 * @property-read User|null $creator
 * @property-read User|null $canceller
 * @property-read Collection<int, SupplierInvoiceItem> $items
 * @property-read Collection<int, PayableInstallment> $installments
 * @property-read Collection<int, PurchaseReceipt> $receipts
 * @property-read Collection<int, SupplierPaymentAllocation> $allocations
 */
class SupplierInvoice extends Model
{
    use HasUuids, LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'supplier_invoices';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'exchange_rate' => 1,
        'prices_include_tax' => true,
        'paid_amount' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'branch_id',
        'kind',
        'provider_id',
        'counterparty_name',
        'document_type',
        'series',
        'document_number',
        'issue_date',
        'due_date',
        'currency_id',
        'exchange_rate',
        'prices_include_tax',
        'tax_rate',
        'taxable_base',
        'exempt_base',
        'unaffected_base',
        'discount_total',
        'tax_total',
        'total',
        'paid_amount',
        'balance',
        'status',
        'purchase_order_id',
        'concept_id',
        'notes',
        'attachment_path',
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
            'kind' => SupplierInvoiceKind::class,
            'document_type' => SupplierDocumentType::class,
            'status' => SupplierInvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:4',
            'prices_include_tax' => 'boolean',
            'tax_rate' => 'decimal:2',
            'taxable_base' => 'decimal:2',
            'exempt_base' => 'decimal:2',
            'unaffected_base' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'paid_amount', 'balance', 'cancel_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Documento del proveedor en formato "SERIE-NÚMERO".
     */
    public function getDocumentLabelAttribute(): string
    {
        return $this->series !== null && $this->series !== ''
            ? "{$this->series}-{$this->document_number}"
            : $this->document_number;
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
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * @return BelongsTo<FinanceConcept, $this>
     */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(FinanceConcept::class, 'concept_id');
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
     * @return HasMany<SupplierInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class, 'supplier_invoice_id')->orderBy('line');
    }

    /**
     * @return HasMany<PayableInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(PayableInstallment::class, 'supplier_invoice_id')->orderBy('number');
    }

    /**
     * @return BelongsToMany<PurchaseReceipt, $this, SupplierInvoiceReceipt>
     */
    public function receipts(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseReceipt::class, 'supplier_invoice_receipts', 'supplier_invoice_id', 'purchase_receipt_id')
            ->using(SupplierInvoiceReceipt::class)
            ->withTimestamps();
    }

    /**
     * @return HasMany<SupplierPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'supplier_invoice_id');
    }
}
