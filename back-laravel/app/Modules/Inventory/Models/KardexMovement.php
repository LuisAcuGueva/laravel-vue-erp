<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\MovementDirection;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * @property string $id
 * @property string $branch_id
 * @property string $warehouse_id
 * @property string $catalog_item_id
 * @property string|null $item_presentation_id
 * @property string|null $reason_id
 * @property MovementDirection $direction
 * @property MovementType $movement_type
 * @property float $quantity
 * @property float|null $presentation_quantity
 * @property float $unit_cost
 * @property float $total_cost
 * @property float $balance_quantity
 * @property float $balance_unit_cost
 * @property float $balance_total_cost
 * @property string $source_type
 * @property string $source_id
 * @property string $source_number
 * @property string|null $transfer_group_id
 * @property string|null $reverses_id
 * @property string|null $user_id
 * @property string|null $notes
 * @property Carbon $moved_at
 * @property Carbon $created_at
 * @property-read Branch $branch
 * @property-read Warehouse $warehouse
 * @property-read CatalogItem $catalogItem
 * @property-read ItemPresentation|null $presentation
 * @property-read WarehouseReason|null $reason
 * @property-read User|null $user
 * @property-read Model $source
 * @property-read KardexMovement|null $reverses
 * @property-read KardexMovement|null $reversedBy
 */
class KardexMovement extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'kardex_movements';

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'direction' => MovementDirection::class,
        'movement_type' => MovementType::class,
        'quantity' => 'float',
        'presentation_quantity' => 'float',
        'unit_cost' => 'float',
        'total_cost' => 'float',
        'balance_quantity' => 'float',
        'balance_unit_cost' => 'float',
        'balance_total_cost' => 'float',
        'moved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('El kardex es inmutable.');
        });

        static::deleting(function () {
            throw new LogicException('El kardex es inmutable.');
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
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
     * @return BelongsTo<WarehouseReason, $this>
     */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(WarehouseReason::class, 'reason_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    /**
     * @return BelongsTo<KardexMovement, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * @return HasOne<KardexMovement, $this>
     */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }
}
