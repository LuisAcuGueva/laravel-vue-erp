<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Clients\Models\Client;
use App\Modules\Clients\Models\ClientContact;
use App\Modules\Finance\Models\Currency;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Sales\Enums\QuotationStatus;
use App\Modules\Users\Models\User;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property string $client_id
 * @property string|null $contact_id
 * @property string|null $seller_id
 * @property Carbon $issue_date
 * @property Carbon $valid_until
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
 * @property QuotationStatus $status
 * @property string|null $notes
 * @property string|null $terms
 * @property string|null $delivery_time
 * @property string|null $payment_terms
 * @property string|null $discount_override_by
 * @property string|null $created_by
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 * @property string|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property string|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property string|null $converted_sale_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 * @property-read Client $client
 * @property-read ClientContact|null $contact
 * @property-read Employee|null $seller
 * @property-read Currency $currency
 * @property-read User|null $createdBy
 * @property-read User|null $discountOverrideBy
 * @property-read User|null $rejectedBy
 * @property-read User|null $cancelledBy
 * @property-read Collection<int, QuotationItem> $items
 */
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'quotations';

    protected $fillable = [
        'number',
        'branch_id',
        'client_id',
        'contact_id',
        'seller_id',
        'issue_date',
        'valid_until',
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
        'status',
        'notes',
        'terms',
        'delivery_time',
        'payment_terms',
        'discount_override_by',
        'created_by',
        'sent_at',
        'accepted_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
        'converted_sale_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'prices_include_tax' => 'boolean',
            'issue_date' => 'date',
            'valid_until' => 'date',
            'exchange_rate' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'global_discount_percent' => 'decimal:2',
            'taxable_base' => 'decimal:2',
            'exempt_base' => 'decimal:2',
            'unaffected_base' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'total_base_currency' => 'decimal:2',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'total',
                'tax_total',
                'discount_total',
                'global_discount_percent',
                'sent_at',
                'accepted_at',
                'rejected_at',
                'cancelled_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<ClientContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'contact_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'seller_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function discountOverrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_override_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('line');
    }

    /**
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeForBranch(Builder $query, string $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Filtra cotizaciones según los permisos de visibilidad del usuario.
     * Si no tiene 'quotations.view_all', ve solo las creadas por él o donde es el vendedor.
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('quotations.view_all')) {
            return $query;
        }

        $employeeId = $user->employee?->id;

        return $query->where(function (Builder $q) use ($user, $employeeId) {
            $q->where('created_by', $user->id);
            if ($employeeId !== null) {
                $q->orWhere('seller_id', $employeeId);
            }
        });
    }

    /**
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || trim($search) === '') {
            return $query;
        }

        $term = '%'.trim($search).'%';

        return $query->where(function (Builder $q) use ($term) {
            $q->where('number', 'ilike', $term)
                ->orWhereHas('client', function (Builder $clientQuery) use ($term) {
                    $clientQuery->where('name', 'ilike', $term)
                        ->orWhere('document_number', 'ilike', $term);
                });
        });
    }

    protected static function newFactory(): QuotationFactory
    {
        return QuotationFactory::new();
    }
}
