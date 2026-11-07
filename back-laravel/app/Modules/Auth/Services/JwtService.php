<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Exceptions\ExpiredTokenException;
use App\Modules\Auth\Models\RefreshToken;
use App\Modules\Auth\Models\Session;
use App\Modules\Users\Models\User;
use App\Support\Auth\UserAgentParser;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class JwtService
{
    private string $secret;

    private string $algorithm;

    private string $issuer;

    private string $audience;

    private int $accessExpiresIn;

    private int $refreshExpiresIn;

    private int $gracePeriodSeconds;

    public function __construct()
    {
        $secret = (string) config('jwt.secret');
        if (strlen($secret) < 32) {
            $secret = (string) config('app.key');
        }
        if (strlen($secret) < 32) {
            $secret = 'default-fallback-jwt-secret-key-at-least-32-bytes!';
        }
        $this->secret = $secret;
        $this->algorithm = (string) config('jwt.algorithm', 'HS256');
        $this->issuer = (string) config('jwt.issuer', 'template_api');
        $this->audience = (string) config('jwt.audience', 'template_client');
        $this->accessExpiresIn = (int) config('jwt.access_expires_in', 900);
        $this->refreshExpiresIn = (int) config('jwt.refresh_expires_in', 604800);
        $this->gracePeriodSeconds = (int) config('jwt.grace_period_seconds', 20);
    }

    public function issueAccessToken(User $user, ?string $sessionId = null): string
    {
        $now = (int) now()->timestamp;
        $roles = $user->getRoleNames()->values()->toArray();
        $permissions = $user->getAllPermissions()->pluck('name')->values()->toArray();

        $claims = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessExpiresIn,
            'sub' => (string) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'roles' => $roles,
            'permissions' => $permissions,
            'tv' => (int) ($user->token_version ?? 1),
            'jti' => (string) Str::uuid(),
        ];

        if ($sessionId !== null) {
            $claims['sid'] = $sessionId;
        }

        return JWT::encode($claims, $this->secret, $this->algorithm);
    }

    /**
     * @return array<string, mixed>
     */
    public function validateAccessToken(string $token): array
    {
        JWT::$timestamp = (int) now()->timestamp;
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));

            return (array) $decoded;
        } catch (ExpiredException $e) {
            $payload = (array) $e->getPayload();
            if (empty($payload)) {
                $segments = explode('.', $token);
                if (count($segments) === 3) {
                    $decodedPayload = json_decode(JWT::urlsafeB64Decode($segments[1]), true);
                    if (is_array($decodedPayload)) {
                        $payload = $decodedPayload;
                    }
                }
            }

            throw new ExpiredTokenException('Token expirado.', $payload);
        } catch (SignatureInvalidException) {
            throw new \RuntimeException('Firma del token inválida.', 401);
        } catch (\Throwable) {
            throw new \RuntimeException('Token inválido.', 401);
        }
    }

    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @return array<string, mixed>
     */
    public function createTokenPair(User $user, ?string $ipAddress = null, ?string $userAgent = null, ?Session $session = null): array
    {
        $ip = $ipAddress ?? (function_exists('request') && request() ? request()->ip() : null);
        $ua = $userAgent ?? (function_exists('request') && request() ? request()->userAgent() : null);
        $uaInfo = UserAgentParser::parse($ua);

        if ($session === null) {
            $session = Session::create([
                'user_id' => $user->id,
                'ip_address' => $ip,
                'user_agent' => $ua ? substr($ua, 0, 500) : null,
                'device_type' => $uaInfo['device_type'],
                'browser' => $uaInfo['browser'],
                'platform' => $uaInfo['platform'],
                'last_used_at' => now(),
                'expires_at' => now()->addSeconds($this->refreshExpiresIn),
            ]);
        } else {
            $session->update([
                'ip_address' => $ip,
                'user_agent' => $ua ? substr($ua, 0, 500) : null,
                'device_type' => $uaInfo['device_type'],
                'browser' => $uaInfo['browser'],
                'platform' => $uaInfo['platform'],
                'last_used_at' => now(),
                'expires_at' => now()->addSeconds($this->refreshExpiresIn),
            ]);
        }

        $accessToken = $this->issueAccessToken($user, $session->id);
        $refreshTokenString = $this->generateRefreshToken();
        $csrfTokenString = $this->generateCsrfToken();

        RefreshToken::create([
            'user_id' => $user->id,
            'session_id' => $session->id,
            'token_hash' => hash('sha256', $refreshTokenString),
            'csrf_token_hash' => hash('sha256', $csrfTokenString),
            'expires_at' => now()->addSeconds($this->refreshExpiresIn),
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshTokenString,
            'csrf_token' => $csrfTokenString,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessExpiresIn,
            'session_id' => $session->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rotateRefreshToken(string $refreshTokenString, ?string $csrfToken): array
    {
        $tokenHash = hash('sha256', $refreshTokenString);

        return DB::transaction(function () use ($tokenHash, $csrfToken) {
            /** @var RefreshToken|null $storedToken */
            $storedToken = RefreshToken::where('token_hash', $tokenHash)->first();

            if (! $storedToken) {
                throw new \RuntimeException('Token de actualización inválido o no encontrado.', 401);
            }

            if (empty($csrfToken) || ! $storedToken->verifyCsrfToken($csrfToken)) {
                throw new \RuntimeException('Token CSRF inválido o no coincide.', 403);
            }

            if ($storedToken->isExpired()) {
                throw new \RuntimeException('Token de actualización expirado.', 401);
            }

            if ($storedToken->isRevoked()) {
                /** @var Session|null $session */
                $session = Session::where('id', $storedToken->session_id)->first();
                if ($session && ! $session->isActive()) {
                    throw new \RuntimeException('Sesión revocada o expirada.', 401);
                }

                if ($storedToken->isWithinGracePeriod()) {
                    $cached = Cache::get('rotated_pair_'.$tokenHash);
                    if (is_array($cached)) {
                        /** @var array<string, mixed> $cached */
                        return $cached;
                    }
                }
                throw new \RuntimeException('Token de actualización revocado.', 401);
            }

            /** @var Session $session */
            $session = Session::where('id', $storedToken->session_id)->lockForUpdate()->firstOrFail();

            if (! $session->isActive()) {
                throw new \RuntimeException('Sesión revocada o expirada.', 401);
            }

            $storedToken->update([
                'revoked_at' => now(),
                'grace_period_expires_at' => now()->addSeconds($this->gracePeriodSeconds),
            ]);

            $user = $storedToken->user;
            if (! $user instanceof User) {
                throw new \RuntimeException('Usuario no encontrado para el token.', 401);
            }

            if ($user->status !== User::STATUS_ACTIVE) {
                throw new \RuntimeException('Usuario inactivo.', 401);
            }

            $ip = function_exists('request') && request() ? request()->ip() : null;
            $ua = function_exists('request') && request() ? request()->userAgent() : null;

            $newPair = $this->createTokenPair(
                user: $user,
                ipAddress: $ip ?? $session->ip_address,
                userAgent: $ua ?? $session->user_agent,
                session: $session,
            );

            Cache::put('rotated_pair_'.$tokenHash, $newPair, now()->addSeconds($this->gracePeriodSeconds));

            return $newPair;
        });
    }

    public function revokeSession(string $sessionId): void
    {
        DB::transaction(function () use ($sessionId) {
            /** @var Session $session */
            $session = Session::where('id', $sessionId)->lockForUpdate()->firstOrFail();

            if ($session->isRevoked()) {
                return;
            }

            $session->update(['revoked_at' => now()]);

            RefreshToken::where('session_id', $session->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        });
    }

    /**
     * @return int Número de sesiones revocadas.
     */
    public function revokeOtherSessions(string $userId, ?string $currentSessionId): int
    {
        return DB::transaction(function () use ($userId, $currentSessionId) {
            $sessions = Session::where('user_id', $userId)
                ->whereNull('revoked_at')
                ->when($currentSessionId !== null, fn ($q) => $q->where('id', '!=', $currentSessionId))
                ->orderBy('id') // orden determinista: evita deadlocks si dos requests revocan en paralelo
                ->lockForUpdate()
                ->get();

            foreach ($sessions as $session) {
                $session->update(['revoked_at' => now()]);
                RefreshToken::where('session_id', $session->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }

            return $sessions->count();
        });
    }

    public function revokeRefreshToken(?string $refreshTokenString): void
    {
        if (empty($refreshTokenString)) {
            return;
        }

        $tokenHash = hash('sha256', $refreshTokenString);
        $storedToken = RefreshToken::where('token_hash', $tokenHash)->first();

        if ($storedToken === null) {
            return;
        }

        $this->revokeSession($storedToken->session_id);
    }

    public function makeRefreshTokenCookie(string $refreshTokenString): Cookie
    {
        $minutes = (int) ($this->refreshExpiresIn / 60);

        return cookie(
            name: 'refresh_token',
            value: $refreshTokenString,
            minutes: $minutes,
            path: config('session.path', '/'),
            domain: config('session.domain'),
            secure: (bool) config('session.secure', false),
            httpOnly: (bool) config('session.http_only', true),
            sameSite: config('session.same_site', 'lax'),
        );
    }

    public function makeForgetRefreshTokenCookie(): Cookie
    {
        return cookie()->forget(
            'refresh_token',
            path: config('session.path', '/'),
            domain: config('session.domain')
        );
    }

    /**
     * Cookie legible por JS (double-submit) con el CSRF vigente del refresh token.
     * El refresh token es compartido por todas las pestañas del navegador, así que su
     * CSRF también debe serlo; guardarlo por pestaña provoca desajustes al rotar.
     */
    public function makeCsrfCookie(string $csrfToken): Cookie
    {
        $minutes = (int) ($this->refreshExpiresIn / 60);

        return cookie(
            name: 'csrf_token',
            value: $csrfToken,
            minutes: $minutes,
            path: config('session.path', '/'),
            domain: config('session.domain'),
            secure: (bool) config('session.secure', false),
            httpOnly: false,
            sameSite: config('session.same_site', 'lax'),
        );
    }

    public function makeForgetCsrfCookie(): Cookie
    {
        return cookie()->forget(
            'csrf_token',
            path: config('session.path', '/'),
            domain: config('session.domain')
        );
    }
}
