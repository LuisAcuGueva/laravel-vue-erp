<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Roles\Models\Role;
use Database\Factories\BoxFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $branch_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Branch|null $branch
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, CashSession> $sessions
 * @property-read CashSession|null $openSession
 */
class Box extends Model
{
    /** @use HasFactory<BoxFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $table = 'boxes';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsToMany<Role, $this, BoxRole>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'box_roles', 'box_id', 'role_id')
            ->using(BoxRole::class)
            ->withTimestamps();
    }

    /**
     * @return HasMany<CashSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'box_id');
    }

    /**
     * @return HasOne<CashSession, $this>
     */
    public function openSession(): HasOne
    {
        return $this->hasOne(CashSession::class, 'box_id')
            ->where('status', CashSessionStatus::Open->value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'description', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function newFactory(): BoxFactory
    {
        return BoxFactory::new();
    }
}
