<?php

declare(strict_types=1);

namespace App\Modules\Clients\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $client_id
 * @property string $name
 * @property string|null $position
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_primary
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Client $client
 */
class ClientContact extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'client_contacts';

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
        'client_id',
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
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
