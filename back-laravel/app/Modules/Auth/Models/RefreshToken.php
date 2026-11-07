<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use App\Modules\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $session_id
 * @property string $token_hash
 * @property string $csrf_token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $grace_period_expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Session $session
 */
class RefreshToken extends Model
{
    use HasUuids;

    protected $table = 'refresh_tokens';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'session_id',
        'token_hash',
        'csrf_token_hash',
        'expires_at',
        'revoked_at',
        'grace_period_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'grace_period_expires_at' => 'datetime',
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
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function isExpired(?CarbonInterface $now = null): bool
    {
        return $this->expires_at->lessThanOrEqualTo($now ?? now());
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isWithinGracePeriod(?CarbonInterface $now = null): bool
    {
        if (! $this->isRevoked() || $this->grace_period_expires_at === null) {
            return false;
        }

        return $this->grace_period_expires_at->greaterThan($now ?? now());
    }

    public function verifyCsrfToken(string $csrfToken): bool
    {
        return hash_equals($this->csrf_token_hash, hash('sha256', $csrfToken));
    }
}
