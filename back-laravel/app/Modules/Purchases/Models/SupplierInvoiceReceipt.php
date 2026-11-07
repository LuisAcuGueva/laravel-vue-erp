<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $id
 * @property string $supplier_invoice_id
 * @property string $purchase_receipt_id
 */
class SupplierInvoiceReceipt extends Pivot
{
    use HasUuids;

    protected $table = 'supplier_invoice_receipts';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'supplier_invoice_id',
        'purchase_receipt_id',
    ];
}
