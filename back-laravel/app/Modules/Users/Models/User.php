<?php

declare(strict_types=1);

namespace App\Modules\Users\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Maintenance\Models\UserBranch;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $status
 * @property int $token_version
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Branch> $branches
 * @property-read Employee|null $employee
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, LogsActivity, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['password', 'remember_token']);
    }

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'token_version',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'token_version' => 'integer',
        ];
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.addcslashes(trim($search), '%_\\').'%';

        return $query->where(function (Builder $q) use ($like, $operator) {
            $q->where('name', $operator, $like)
                ->orWhere('email', $operator, $like);
        });
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeRole(Builder $query, ?string $role): Builder
    {
        if (blank($role)) {
            return $query;
        }

        return $query->whereHas('roles', function (Builder $q) use ($role) {
            $q->where('name', $role);
        });
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWithoutEmployee(Builder $query, ?bool $withoutEmployee): Builder
    {
        if (! $withoutEmployee) {
            return $query;
        }

        return $query->whereDoesntHave('employee');
    }

    /**
     * @return BelongsToMany<Branch, $this, UserBranch>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches')
            ->using(UserBranch::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function defaultBranch(): ?Branch
    {
        if ($this->relationLoaded('branches')) {
            /** @var Branch|null $default */
            $default = $this->branches->first(fn (Branch $b): bool => (bool) ($b->pivot->is_default ?? false));

            return $default;
        }

        /** @var Branch|null $branch */
        $branch = $this->branches()->wherePivot('is_default', true)->first();

        return $branch;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    /**
     * @param  mixed  $roleOrPermission
     */
    protected function ensureModelSharesGuard($roleOrPermission): void
    {
        // No-op: roles y permisos no usan guard
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
