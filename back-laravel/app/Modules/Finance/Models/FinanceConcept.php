<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\ConceptDirection;
use App\Modules\Finance\Enums\FinancialAccountType;
use App\Modules\Finance\Enums\MoneyDirection;
use Database\Factories\FinanceConceptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property ConceptDirection $direction
 * @property string|null $financial_account_id
 * @property bool $is_system
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read FinancialAccount|null $financialAccount
 */
class FinanceConcept extends Model
{
    /** @use HasFactory<FinanceConceptFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'finance_concepts';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_system' => false,
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'direction',
        'financial_account_id',
        'is_system',
        'is_active',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => ConceptDirection::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    /**
     * @param  Builder<FinanceConcept>  $query
     * @return Builder<FinanceConcept>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<FinanceConcept>  $query
     * @return Builder<FinanceConcept>
     */
    public function scopeDirection(Builder $query, ConceptDirection|MoneyDirection|string|null $direction): Builder
    {
        if ($direction === null || $direction === '') {
            return $query;
        }

        if ($direction instanceof MoneyDirection || in_array($direction, ['in', 'out'], true)) {
            $moneyDirection = $direction instanceof MoneyDirection
                ? $direction
                : MoneyDirection::from($direction);

            $allowed = array_filter(
                ConceptDirection::cases(),
                fn (ConceptDirection $cd) => $cd->allows($moneyDirection)
            );
            $values = array_map(fn (ConceptDirection $cd) => $cd->value, $allowed);

            return $query->whereIn('direction', $values);
        }

        $value = $direction instanceof ConceptDirection ? $direction->value : $direction;

        return $query->where('direction', $value);
    }

    /**
     * @param  Builder<FinanceConcept>  $query
     * @return Builder<FinanceConcept>
     */
    public function scopeWithoutAccount(Builder $query): Builder
    {
        return $query->whereNull('financial_account_id');
    }

    /**
     * Incluye los conceptos sin cuenta: se aceptan mientras la imputación no sea obligatoria.
     *
     * @param  Builder<FinanceConcept>  $query
     * @return Builder<FinanceConcept>
     */
    public function scopeAccountType(Builder $query, FinancialAccountType|string|null $type): Builder
    {
        if ($type === null) {
            return $query;
        }

        $value = $type instanceof FinancialAccountType ? $type->value : $type;

        return $query->where(fn (Builder $w) => $w
            ->whereNull('financial_account_id')
            ->orWhereHas('financialAccount', fn ($a) => $a->where('type', $value)));
    }

    /**
     * @param  Builder<FinanceConcept>  $query
     * @return Builder<FinanceConcept>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $query->where(function (Builder $inner) use ($like, $operator) {
            $inner->where('code', $operator, $like)
                ->orWhere('name', $operator, $like);
        });
    }

    protected static function newFactory(): FinanceConceptFactory
    {
        return FinanceConceptFactory::new();
    }
}
