<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $provider_id
 * @property string $name
 * @property string|null $position
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_primary
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Provider $provider
 */
class ProviderContact extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'provider_contacts';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_primary' => false,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'name',
        'position',
        'phone',
        'email',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }
}
