<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Finance\Models\BankMovement;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sale_id
 * @property string $payment_method_id
 * @property string|null $cash_session_id
 * @property string $amount
 * @property string|null $received_amount
 * @property string $change_amount
 * @property string|null $reference
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Sale $sale
 * @property-read PaymentMethod $paymentMethod
 * @property-read CashSession|null $cashSession
 * @property-read Collection<int, CashMovement> $cashMovements
 * @property-read Collection<int, BankMovement> $bankMovements
 */
class SalePayment extends Model
{
    use HasUuids;

    protected $table = 'sale_payments';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_id',
        'payment_method_id',
        'cash_session_id',
        'amount',
        'received_amount',
        'change_amount',
        'reference',
        'status',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'change_amount' => '0.00',
        'status' => 'completed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    /**
     * @return MorphMany<CashMovement, $this>
     */
    public function cashMovements(): MorphMany
    {
        return $this->morphMany(CashMovement::class, 'source', 'source_type', 'source_id');
    }

    /**
     * @return MorphMany<BankMovement, $this>
     */
    public function bankMovements(): MorphMany
    {
        return $this->morphMany(BankMovement::class, 'source', 'source_type', 'source_id');
    }
}
