<?php

declare(strict_types=1);

namespace App\Modules\Profile\Services;

use App\Modules\Auth\Models\Session;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    public function __construct(
        protected JwtService $jwtService
    ) {}

    /**
     * @param  array{name?: string, email?: string}  $attributes
     */
    public function updateProfile(User $user, array $attributes): User
    {
        $user->update($attributes);

        $fresh = $user->fresh() ?? $user;

        return $fresh->load('roles');
    }

    /**
     * Actualiza la contraseña, incrementa token_version y revoca todas las sesiones previas.
     *
     * @return array<string, mixed>
     */
    public function updatePassword(User $user, string $newPassword): array
    {
        return DB::transaction(function () use ($user, $newPassword) {
            $user->update([
                'password' => $newPassword,
            ]);

            // Incrementar token_version para invalidar access tokens previos
            $user->increment('token_version');

            // Revocar todas las sesiones previas del usuario
            Session::where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->get()
                ->each(fn (Session $session) => $this->jwtService->revokeSession($session->id));

            // Crear un nuevo par de tokens para la nueva sesión
            $freshUser = $user->fresh() ?? $user;

            return $this->jwtService->createTokenPair($freshUser);
        });
    }

    /**
     * Lista las sesiones activas del usuario, marcando cuál es la actual.
     *
     * @return Collection<int, array{id: string, ip_address: string|null, device_type: string, browser: string|null, platform: string|null, created_at: string, last_used_at: string, is_current: bool}>
     */
    public function listSessions(User $user, ?string $currentSessionId = null): Collection
    {
        /** @var Collection<int, Session> $sessions */
        $sessions = Session::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('last_used_at')
            ->get();

        return $sessions->map(fn (Session $session) => [
            'id' => $session->id,
            'ip_address' => $session->ip_address,
            'device_type' => $session->device_type,
            'browser' => $session->browser,
            'platform' => $session->platform,
            'created_at' => $session->created_at?->toIso8601String(),
            'last_used_at' => $session->last_used_at->toIso8601String(),
            'is_current' => $currentSessionId !== null && $session->id === $currentSessionId,
        ]);
    }

    /**
     * Revoca todas las sesiones del usuario excepto la sesión actual.
     */
    public function revokeOtherSessions(User $user, ?string $currentSessionId = null): int
    {
        return $this->jwtService->revokeOtherSessions($user->id, $currentSessionId);
    }
}
