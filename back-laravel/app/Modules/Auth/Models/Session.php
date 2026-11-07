<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use App\Modules\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sesión lógica de un dispositivo/navegador. Sobrevive a la rotación de
 * refresh tokens: el `id` y el `created_at` de esta fila son estables desde
 * el login hasta que la sesión se revoca; `refresh_tokens.session_id` enlaza
 * cada fila rotada a la misma sesión.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $device_type
 * @property string|null $browser
 * @property string|null $platform
 * @property Carbon $last_used_at
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read HasMany<RefreshToken, $this> $refreshTokens
 */
class Session extends Model
{
    use HasUuids;

    protected $table = 'sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    public const DEVICE_DESKTOP = 'desktop';

    public const DEVICE_MOBILE = 'mobile';

    public const DEVICE_TABLET = 'tablet';

    public const DEVICE_UNKNOWN = 'unknown';

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<RefreshToken, $this>
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(?CarbonInterface $now = null): bool
    {
        return $this->expires_at->lessThanOrEqualTo($now ?? now());
    }

    public function isActive(?CarbonInterface $now = null): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired($now);
    }
}
