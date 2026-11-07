<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $supplier_payment_id
 * @property string $payable_installment_id
 * @property string $supplier_invoice_id
 * @property string $amount
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SupplierPayment $payment
 * @property-read PayableInstallment $installment
 * @property-read SupplierInvoice $invoice
 */
class SupplierPaymentAllocation extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'supplier_payment_allocations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_payment_id',
        'payable_installment_id',
        'supplier_invoice_id',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SupplierPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    /**
     * @return BelongsTo<PayableInstallment, $this>
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(PayableInstallment::class, 'payable_installment_id');
    }

    /**
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }
}
