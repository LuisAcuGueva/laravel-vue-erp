<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Asignación de un cobro a una cuota por cobrar específica de una venta.
 *
 * @property string $id
 * @property string $collection_id
 * @property string $receivable_installment_id
 * @property string $sale_id
 * @property string $amount
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection $collection
 * @property-read ReceivableInstallment $installment
 * @property-read Sale $sale
 */
class CollectionAllocation extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'collection_allocations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'collection_id',
        'receivable_installment_id',
        'sale_id',
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
     * @return BelongsTo<Collection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'collection_id');
    }

    /**
     * @return BelongsTo<ReceivableInstallment, $this>
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(ReceivableInstallment::class, 'receivable_installment_id');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
