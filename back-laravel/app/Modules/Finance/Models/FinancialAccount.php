<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\FinancialAccountType;
use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property FinancialAccountType $type
 * @property string|null $parent_id
 * @property int $level
 * @property bool $is_postable
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read FinancialAccount|null $parent
 */
class FinancialAccount extends Model
{
    /** @use HasFactory<FinancialAccountFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'financial_accounts';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'level' => 1,
        'is_postable' => true,
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'level',
        'is_postable',
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
            'type' => FinancialAccountType::class,
            'level' => 'integer',
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<FinancialAccount, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    /**
     * @return HasMany<FinanceConcept, $this>
     */
    public function concepts(): HasMany
    {
        return $this->hasMany(FinanceConcept::class, 'financial_account_id');
    }

    /**
     * @return list<string>
     */
    public function ancestorIds(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current !== null) {
            $ancestors[] = $current->id;
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * @param  Builder<FinancialAccount>  $query
     * @return Builder<FinancialAccount>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<FinancialAccount>  $query
     * @return Builder<FinancialAccount>
     */
    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_postable', true);
    }

    /**
     * @param  Builder<FinancialAccount>  $query
     * @return Builder<FinancialAccount>
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

    protected static function newFactory(): FinancialAccountFactory
    {
        return FinancialAccountFactory::new();
    }
}
