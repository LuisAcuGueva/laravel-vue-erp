<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Models;

use App\Modules\Finance\Models\Box;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Users\Models\User;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property string|null $ubigeo
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_main
 * @property bool $is_active
 * @property int|null $users_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property UserBranch $pivot
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Employee> $employees
 * @property-read Collection<int, Warehouse> $warehouses
 * @property-read Collection<int, Box> $boxes
 */
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'branches';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_main' => false,
        'is_active' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'address',
        'ubigeo',
        'phone',
        'email',
        'is_main',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: static fn (string $value): string => strtoupper(trim($value)),
        );
    }

    /**
     * @return BelongsToMany<User, $this, UserBranch>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branches')
            ->using(UserBranch::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'branch_id');
    }

    /**
     * @return HasMany<Warehouse, $this>
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'branch_id');
    }

    /**
     * @return HasMany<Box, $this>
     */
    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class, 'branch_id');
    }

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
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

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }
}
