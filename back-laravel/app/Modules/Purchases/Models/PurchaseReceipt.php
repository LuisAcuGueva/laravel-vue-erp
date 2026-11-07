<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Purchases\Enums\ProviderDocumentType;
use App\Modules\Purchases\Enums\PurchaseReceiptStatus;
use App\Modules\Users\Models\User;
use Database\Factories\PurchaseReceiptFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $number
 * @property string $branch_id
 * @property string $purchase_order_id
 * @property string $provider_id
 * @property string $warehouse_id
 * @property Carbon $receipt_date
 * @property ProviderDocumentType $provider_document_type
 * @property string|null $provider_document_number
 * @property string|null $notes
 * @property PurchaseReceiptStatus $status
 * @property string $total_cost
 * @property string|null $created_by
 * @property string|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 * @property-read PurchaseOrder $order
 * @property-read Provider $provider
 * @property-read Warehouse $warehouse
 * @property-read User|null $creator
 * @property-read User|null $confirmer
 * @property-read User|null $canceller
 * @property-read Collection<int, PurchaseReceiptItem> $items
 * @property-read Collection<int, KardexMovement> $kardexMovements
 * @property-read Collection<int, SupplierInvoice> $supplierInvoices
 */
class PurchaseReceipt extends Model
{
    /** @use HasFactory<PurchaseReceiptFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'purchase_receipts';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'total_cost' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'branch_id',
        'purchase_order_id',
        'provider_id',
        'warehouse_id',
        'receipt_date',
        'provider_document_type',
        'provider_document_number',
        'notes',
        'status',
        'total_cost',
        'created_by',
        'confirmed_by',
        'confirmed_at',
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
            'receipt_date' => 'date',
            'provider_document_type' => ProviderDocumentType::class,
            'status' => PurchaseReceiptStatus::class,
            'total_cost' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'cancel_reason'])
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
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
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
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<PurchaseReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class, 'purchase_receipt_id')->orderBy('line');
    }

    /**
     * @return MorphMany<KardexMovement, $this>
     */
    public function kardexMovements(): MorphMany
    {
        return $this->morphMany(KardexMovement::class, 'source');
    }

    /**
     * @return BelongsToMany<SupplierInvoice, $this, SupplierInvoiceReceipt>
     */
    public function supplierInvoices(): BelongsToMany
    {
        return $this->belongsToMany(SupplierInvoice::class, 'supplier_invoice_receipts', 'purchase_receipt_id', 'supplier_invoice_id')
            ->using(SupplierInvoiceReceipt::class)
            ->withTimestamps();
    }

    protected static function newFactory(): PurchaseReceiptFactory
    {
        return PurchaseReceiptFactory::new();
    }
}
