<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Database\Factories\CashCountDetailFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $cash_session_id
 * @property string $denomination
 * @property int $quantity
 * @property string $subtotal
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CashSession|null $session
 */
class CashCountDetail extends Model
{
    /** @use HasFactory<CashCountDetailFactory> */
    use HasFactory, HasUuids;

    protected $table = 'cash_count_details';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cash_session_id',
        'denomination',
        'quantity',
        'subtotal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'denomination' => 'decimal:2',
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }
}
