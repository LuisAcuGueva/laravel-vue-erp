<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Models;

use App\Modules\Users\Models\User;
use App\Support\Identity\DocumentType;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property DocumentType $document_type
 * @property string $document_number
 * @property string $first_name
 * @property string $last_name
 * @property-read string $full_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property Carbon|null $birth_date
 * @property Carbon|null $hire_date
 * @property string|null $position
 * @property string|null $area_id
 * @property string|null $branch_id
 * @property string|null $user_id
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Area|null $area
 * @property-read Branch|null $branch
 * @property-read User|null $user
 */
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'employees';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
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
        'document_type',
        'document_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'birth_date',
        'hire_date',
        'position',
        'area_id',
        'branch_id',
        'user_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'birth_date' => 'date',
            'hire_date' => 'date',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
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
            $inner->where('first_name', $operator, $like)
                ->orWhere('last_name', $operator, $like)
                ->orWhere('document_number', $operator, $like)
                ->orWhereRaw("first_name || ' ' || last_name {$operator} ?", [$like]);
        });
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeForBranch(Builder $query, ?string $branchId): Builder
    {
        if (! blank($branchId)) {
            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeForArea(Builder $query, ?string $areaId): Builder
    {
        if (! blank($areaId)) {
            $query->where('area_id', $areaId);
        }

        return $query;
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeHasUser(Builder $query, ?bool $hasUser): Builder
    {
        if ($hasUser === true) {
            $query->whereNotNull('user_id');
        } elseif ($hasUser === false) {
            $query->whereNull('user_id');
        }

        return $query;
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }
}
