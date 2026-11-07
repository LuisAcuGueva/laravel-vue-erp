<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Audit\Concerns\LogsSystemActivity;
use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Inventory\Models\KardexMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Sales\Enums\CreditNoteReason;
use App\Modules\Sales\Enums\CreditNoteSettlementMode;
use App\Modules\Sales\Enums\CreditNoteStatus;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $branch_id
 * @property string $sale_id
 * @property string $document_series_id
 * @property string $series
 * @property int $number
 * @property string $full_number
 * @property string $issue_date
 * @property CarbonImmutable $issued_at
 * @property CreditNoteReason $reason_code
 * @property string $reason_description
 * @property string $client_id
 * @property string|null $client_document_type
 * @property string|null $client_document_number
 * @property string $client_name
 * @property string|null $client_address
 * @property string $currency_id
 * @property string $exchange_rate
 * @property string $tax_rate
 * @property bool $prices_include_tax
 * @property string $taxable_base
 * @property string $exempt_base
 * @property string $unaffected_base
 * @property string $tax_total
 * @property string $total
 * @property string $applied_to_balance
 * @property string $refunded_amount
 * @property CreditNoteSettlementMode $settlement_mode
 * @property string|null $refund_payment_method_id
 * @property string|null $refund_cash_session_id
 * @property string|null $warehouse_id
 * @property CreditNoteStatus $status
 * @property string $einvoice_status
 * @property int $einvoice_attempts
 * @property array<string, mixed>|null $einvoice_response
 * @property CarbonImmutable|null $einvoice_sent_at
 * @property string|null $created_by
 * @property string|null $cancelled_by
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Branch $branch
 * @property-read Sale $sale
 * @property-read DocumentSeries $documentSeries
 * @property-read Client $client
 * @property-read Currency $currency
 * @property-read PaymentMethod|null $refundPaymentMethod
 * @property-read CashSession|null $refundCashSession
 * @property-read Warehouse|null $warehouse
 * @property-read User|null $creator
 * @property-read User|null $canceller
 * @property-read Collection<int, CreditNoteItem> $items
 * @property-read Collection<int, CreditNoteInstallmentAdjustment> $installmentAdjustments
 * @property-read Collection<int, KardexMovement> $kardexMovements
 * @property-read Collection<int, CashMovement> $cashMovements
 * @property-read Collection<int, BankMovement> $bankMovements
 */
class CreditNote extends Model
{
    use HasUuids;
    use LogsActivity;
    use LogsSystemActivity;

    protected $table = 'credit_notes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'branch_id',
        'sale_id',
        'document_series_id',
        'series',
        'number',
        'full_number',
        'issue_date',
        'issued_at',
        'reason_code',
        'reason_description',
        'client_id',
        'client_document_type',
        'client_document_number',
        'client_name',
        'client_address',
        'currency_id',
        'exchange_rate',
        'tax_rate',
        'prices_include_tax',
        'taxable_base',
        'exempt_base',
        'unaffected_base',
        'tax_total',
        'total',
        'applied_to_balance',
        'refunded_amount',
        'settlement_mode',
        'refund_payment_method_id',
        'refund_cash_session_id',
        'warehouse_id',
        'status',
        'einvoice_status',
        'einvoice_attempts',
        'einvoice_response',
        'einvoice_sent_at',
        'created_by',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'reason_code' => CreditNoteReason::class,
        'status' => CreditNoteStatus::class,
        'settlement_mode' => CreditNoteSettlementMode::class,
        'issue_date' => 'date:Y-m-d',
        'issued_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
        'einvoice_sent_at' => 'immutable_datetime',
        'einvoice_response' => 'array',
        'einvoice_attempts' => 'integer',
        'prices_include_tax' => 'boolean',
        'exchange_rate' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'taxable_base' => 'decimal:2',
        'exempt_base' => 'decimal:2',
        'unaffected_base' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'applied_to_balance' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
    ];

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
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /**
     * @return BelongsTo<DocumentSeries, $this>
     */
    public function documentSeries(): BelongsTo
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
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function refundPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'refund_payment_method_id');
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function refundCashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'refund_cash_session_id');
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
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<CreditNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class, 'credit_note_id')->orderBy('line');
    }

    /**
     * @return HasMany<CreditNoteInstallmentAdjustment, $this>
     */
    public function installmentAdjustments(): HasMany
    {
        return $this->hasMany(CreditNoteInstallmentAdjustment::class, 'credit_note_id');
    }

    /**
     * @return MorphMany<KardexMovement, $this>
     */
    public function kardexMovements(): MorphMany
    {
        return $this->morphMany(KardexMovement::class, 'source');
    }

    /**
     * @return MorphMany<CashMovement, $this>
     */
    public function cashMovements(): MorphMany
    {
        return $this->morphMany(CashMovement::class, 'source');
    }

    /**
     * @return MorphMany<BankMovement, $this>
     */
    public function bankMovements(): MorphMany
    {
        return $this->morphMany(BankMovement::class, 'source');
    }
}
