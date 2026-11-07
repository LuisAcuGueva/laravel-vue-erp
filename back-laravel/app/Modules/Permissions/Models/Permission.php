<?php

declare(strict_types=1);

namespace App\Modules\Permissions\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission as SpatiePermission;

use function Illuminate\Support\enum_value;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $module
 * @property string|null $description
 * @property int|null $roles_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Permission extends SpatiePermission
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
        'slug',
        'module',
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

    /**
     * @param  array<string, mixed>  $attributes
     * @return static
     */
    public static function create(array $attributes = [])
    {
        unset($attributes['guard_name']);

        if (isset($attributes['slug'])) {
            $attributes['slug'] = enum_value($attributes['slug']);
        } elseif (isset($attributes['name'])) {
            $attributes['slug'] = enum_value($attributes['name']);
        }

        $attributes['name'] = enum_value($attributes['name'] ?? $attributes['slug']);
        $attributes['module'] = $attributes['module'] ?? (str_contains($attributes['slug'], '.') ? explode('.', $attributes['slug'])[0] : 'general');

        if (static::query()->where('slug', $attributes['slug'])->first()) {
            throw PermissionAlreadyExists::create($attributes['slug'], 'default');
        }

        /** @var static $permission */
        $permission = static::query()->create($attributes);

        return $permission;
    }

    public static function findByName(BackedEnum|string $name, ?string $guardName = null): PermissionContract
    {
        $slug = enum_value($name);

        $permission = static::query()->where('slug', $slug)->first();

        if (! $permission) {
            throw PermissionDoesNotExist::create($slug, $guardName ?? 'default');
        }

        return $permission;
    }

    public static function findById(int|string $id, ?string $guardName = null): PermissionContract
    {
        $permission = static::query()->find($id);

        if (! $permission) {
            throw PermissionDoesNotExist::withId($id, $guardName ?? 'default');
        }

        return $permission;
    }

    public static function findOrCreate(BackedEnum|string $name, ?string $guardName = null): PermissionContract
    {
        $slug = enum_value($name);

        $permission = static::query()->where('slug', $slug)->first();

        if (! $permission) {
            return static::query()->create([
                'name' => $slug,
                'slug' => $slug,
                'module' => str_contains($slug, '.') ? explode('.', $slug)[0] : 'general',
            ]);
        }

        return $permission;
    }

    /**
     * @param  Builder<Permission>  $query
     * @return Builder<Permission>
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
                ->orWhere('slug', $operator, $like)
                ->orWhere('module', $operator, $like)
                ->orWhere('description', $operator, $like);
        });
    }

    /**
     * @param  Builder<Permission>  $query
     * @return Builder<Permission>
     */
    public function scopeModule(Builder $query, ?string $module): Builder
    {
        if ($module === null || trim($module) === '') {
            return $query;
        }

        $clean = trim($module);

        return $query->where(function (Builder $q) use ($clean) {
            $q->where('module', $clean)
                ->orWhereRaw('LOWER(module) = LOWER(?)', [$clean]);
        });
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
