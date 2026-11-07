<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use App\Modules\Inventory\Enums\TaxType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $supplier_invoice_id
 * @property int $line
 * @property string $description
 * @property string|null $catalog_item_id
 * @property string|null $item_presentation_id
 * @property string|null $purchase_receipt_item_id
 * @property string $quantity
 * @property string $unit_price
 * @property string $discount_percent
 * @property TaxType $tax_type
 * @property string $line_subtotal
 * @property string $line_tax
 * @property string $line_total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SupplierInvoice $invoice
 * @property-read CatalogItem|null $catalogItem
 * @property-read ItemPresentation|null $presentation
 * @property-read PurchaseReceiptItem|null $receiptItem
 */
class SupplierInvoiceItem extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'supplier_invoice_items';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'discount_percent' => 0,
        'tax_type' => 'taxable',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_invoice_id',
        'line',
        'description',
        'catalog_item_id',
        'item_presentation_id',
        'purchase_receipt_item_id',
        'quantity',
        'unit_price',
        'discount_percent',
        'tax_type',
        'line_subtotal',
        'line_tax',
        'line_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'tax_type' => TaxType::class,
            'line_subtotal' => 'decimal:2',
            'line_tax' => 'decimal:2',
            'line_total' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    /**
     * @return BelongsTo<ItemPresentation, $this>
     */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ItemPresentation::class, 'item_presentation_id');
    }

    /**
     * @return BelongsTo<PurchaseReceiptItem, $this>
     */
    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceiptItem::class, 'purchase_receipt_item_id');
    }
}
