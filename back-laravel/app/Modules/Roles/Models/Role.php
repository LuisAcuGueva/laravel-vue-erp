<?php

declare(strict_types=1);

namespace App\Modules\Roles\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role as SpatieRole;

use function Illuminate\Support\enum_value;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property bool|null $is_protected
 * @property int|null $users_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Role extends SpatieRole
{
    use HasUuids;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        unset($this->attributes['guard_name']);
    }

    public function setGuardNameAttribute(mixed $value): void
    {
        // Guard name no se persiste en base de datos.
    }

    public function getGuardNameAttribute(): string
    {
        return 'default';
    }

    /**
     * @return Collection<int, string>
     */
    public function getGuardNames(): Collection
    {
        return collect(['web', 'api', 'default']);
    }

    protected function ensureModelSharesGuard($roleOrPermission): void
    {
        // No-op: desacoplado del guard
    }

    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        if ($this->getWildcardClass()) {
            return $this->hasWildcardPermission($permission, $guardName);
        }

        $permission = $this->filterPermission($permission, $guardName);

        return $this->permissions->contains($permission->getKeyName(), $permission->getKey());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return static
     */
    public static function create(array $attributes = [])
    {
        unset($attributes['guard_name']);
        $attributes['name'] = enum_value($attributes['name']);

        if (static::query()->where('name', $attributes['name'])->first()) {
            throw RoleAlreadyExists::create($attributes['name'], 'default');
        }

        /** @var static $role */
        $role = static::query()->create($attributes);

        return $role;
    }

    public static function findByName(BackedEnum|string $name, ?string $guardName = null): RoleContract
    {
        $name = enum_value($name);
        $role = static::query()->where('name', $name)->first();

        if (! $role) {
            throw RoleDoesNotExist::named($name, $guardName ?? 'default');
        }

        return $role;
    }

    public static function findById(int|string $id, ?string $guardName = null): RoleContract
    {
        $role = static::query()->find($id);

        if (! $role) {
            throw RoleDoesNotExist::withId($id, $guardName ?? 'default');
        }

        return $role;
    }

    public static function findOrCreate(BackedEnum|string $name, ?string $guardName = null): RoleContract
    {
        $name = enum_value($name);
        $role = static::query()->where('name', $name)->first();

        if (! $role) {
            return static::query()->create(['name' => $name]);
        }

        return $role;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected static function findByParam(array $params = []): ?RoleContract
    {
        unset($params['guard_name']);
        $query = static::query();

        foreach ($params as $key => $value) {
            $query->where($key, $value);
        }

        return $query->first();
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $query->where(function (Builder $q) use ($like, $operator) {
            $q->where('name', $operator, $like)
                ->orWhere('description', $operator, $like);
        });
    }

    public function getIsProtectedAttribute(): bool
    {
        $protected = config('permission.protected_roles', ['admin']);

        return in_array($this->name, $protected, true);
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
