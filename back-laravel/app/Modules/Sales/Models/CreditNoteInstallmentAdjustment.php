<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $credit_note_id
 * @property string $receivable_installment_id
 * @property string $amount
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read CreditNote $creditNote
 * @property-read ReceivableInstallment $installment
 */
class CreditNoteInstallmentAdjustment extends Model
{
    use HasUuids;

    protected $table = 'credit_note_installment_adjustments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'credit_note_id',
        'receivable_installment_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    /**
     * @return BelongsTo<ReceivableInstallment, $this>
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(ReceivableInstallment::class, 'receivable_installment_id');
    }
}
