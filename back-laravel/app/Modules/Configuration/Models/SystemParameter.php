<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\ParameterValueType;
use Database\Factories\SystemParameterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $group_key
 * @property string $item_key
 * @property-read string $key
 * @property string $label
 * @property string|null $description
 * @property ParameterValueType $value_type
 * @property mixed $value
 * @property bool $is_editable
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SystemParameter extends Model
{
    /** @use HasFactory<SystemParameterFactory> */
    use HasFactory, HasUuids, LogsActivity;

    protected $table = 'system_parameters';

    protected $keyType = 'string';

    public $incrementing = false;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['value', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'group_key',
        'item_key',
        'label',
        'description',
        'value_type',
        'value',
        'is_editable',
        'is_active',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'value_type' => ParameterValueType::class,
            'is_editable' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Accessor para la clave compuesta del parámetro (group_key.item_key).
     *
     * @return Attribute<string, never>
     */
    protected function key(): Attribute
    {
        return Attribute::make(
            get: fn (): string => "{$this->group_key}.{$this->item_key}",
        );
    }

    /**
     * Filtra los parámetros por group_key si se proporciona.
     *
     * @param  Builder<SystemParameter>  $query
     * @return Builder<SystemParameter>
     */
    public function scopeGroup(Builder $query, ?string $group): Builder
    {
        if ($group === null || trim($group) === '') {
            return $query;
        }

        return $query->where('group_key', trim($group));
    }

    /**
     * Crea una nueva instancia de factory para este modelo.
     */
    protected static function newFactory(): SystemParameterFactory
    {
        return SystemParameterFactory::new();
    }
}
