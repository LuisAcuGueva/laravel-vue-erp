<?php

declare(strict_types=1);

namespace App\Modules\Auth\Middleware;

use App\Modules\Auth\Exceptions\ExpiredTokenException;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Users\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Auth\CurrentSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(
        protected JwtService $jwtService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token || trim($token) === '') {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado. Token de acceso no proporcionado.',
                'errors' => null,
            ], 401);
        }

        /** @var array<string, mixed>|null $rotatedTokens */
        $rotatedTokens = null;

        try {
            $claims = $this->jwtService->validateAccessToken($token);
        } catch (ExpiredTokenException $e) {
            $refreshResult = $this->attemptSilentTokenRefresh($request, $e);
            if ($refreshResult instanceof Response) {
                return $refreshResult;
            }

            [$claims, $rotatedTokens] = $refreshResult;
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => null,
            ], 401);
        }

        $userId = $claims['sub'] ?? null;
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido. Sujeto no encontrado.',
                'errors' => null,
            ], 401);
        }

        $user = User::query()->find($userId);
        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
                'errors' => null,
            ], 401);
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo.',
                'errors' => null,
            ], 401);
        }

        $tokenVersion = $claims['tv'] ?? null;
        if ($tokenVersion !== null && (int) $tokenVersion !== (int) $user->token_version) {
            return response()->json([
                'success' => false,
                'message' => 'Token revocado o versión no válida.',
                'errors' => null,
            ], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        $sessionId = $rotatedTokens['session_id'] ?? ($claims['sid'] ?? null);

        if (is_string($sessionId) && $sessionId !== '') {
            $request->attributes->set('session_id', $sessionId);
            app(CurrentSession::class)->set($sessionId);
            app(AuditContext::class)->setSessionId($sessionId);
        }

        if ($rotatedTokens !== null) {
            $request->headers->set('Authorization', 'Bearer '.$rotatedTokens['access_token']);
        }

        $response = $next($request);

        if ($rotatedTokens !== null) {
            $this->attachRotatedTokenHeadersAndCookies($response, $rotatedTokens);
        }

        return $response;
    }

    /**
     * Intenta renovar silenciosamente el par de tokens cuando el access token ya expiró
     * pero el refresh token y el token CSRF aún son válidos.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|Response
     */
    private function attemptSilentTokenRefresh(
        Request $request,
        ExpiredTokenException $expiredException
    ): array|Response {
        $refreshToken = $this->extractRefreshToken($request);
        if ($refreshToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Token expirado.',
                'errors' => null,
            ], 401);
        }

        $csrfToken = (string) ($request->header('X-CSRF-TOKEN')
            ?: ($request->header('x-csrf-token')
            ?: ($request->input('csrf_token') ?? '')));

        if ($csrfToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Token expirado.',
                'errors' => null,
            ], 401);
        }

        $rawPayload = $expiredException->rawPayload();
        $tokenHash = hash('sha256', $refreshToken);
        $cacheKey = 'rotated_pair_'.$tokenHash;
        $lockKey = 'silent_refresh_lock_'.$tokenHash;
        $gracePeriod = (int) config('jwt.grace_period_seconds', 20);

        try {
            if (function_exists('app') && app()->bound('cache')) {
                // Si una petición concurrente ya rotó este refresh token dentro del periodo de gracia, reutilizar el par
                $cached = Cache::get($cacheKey);
                if (is_array($cached) && isset($cached['access_token'])) {
                    $newClaims = $this->jwtService->validateAccessToken($cached['access_token']);

                    return [$newClaims, $cached];
                }

                try {
                    // Bloqueo atómico para que solo UNA petición concurrente ejecute la rotación en base de datos
                    $tokens = Cache::lock($lockKey, 10)->block(5, function () use ($cacheKey, $refreshToken, $csrfToken, $gracePeriod) {
                        $cached = Cache::get($cacheKey);
                        if (is_array($cached) && isset($cached['access_token'])) {
                            return $cached;
                        }

                        $rotated = $this->jwtService->rotateRefreshToken(
                            refreshTokenString: $refreshToken,
                            csrfToken: $csrfToken,
                        );

                        Cache::put($cacheKey, $rotated, now()->addSeconds($gracePeriod));

                        return $rotated;
                    });
                } catch (\BadMethodCallException) {
                    // Si el driver de caché no soporta locks (ej: array store en ciertos entornos)
                    $tokens = $this->jwtService->rotateRefreshToken(
                        refreshTokenString: $refreshToken,
                        csrfToken: $csrfToken,
                    );
                    Cache::put($cacheKey, $tokens, now()->addSeconds($gracePeriod));
                }
            } else {
                $tokens = $this->jwtService->rotateRefreshToken(
                    refreshTokenString: $refreshToken,
                    csrfToken: $csrfToken,
                );
            }

            if (! is_array($tokens) || ! isset($tokens['access_token'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token expirado.',
                    'errors' => null,
                ], 401);
            }

            $newClaims = $this->jwtService->validateAccessToken($tokens['access_token']);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Token expirado.',
                'errors' => null,
            ], 401);
        }

        // Verificar que el sub del token expirado coincida con el usuario del token rotado
        $expiredSub = (string) ($rawPayload['sub'] ?? '');
        if ($expiredSub !== '' && (string) ($newClaims['sub'] ?? '') !== $expiredSub) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido.',
                'errors' => null,
            ], 401);
        }

        return [$newClaims, $tokens];
    }

    private function extractRefreshToken(Request $request): string
    {
        $refreshToken = (string) ($request->cookie('refresh_token')
            ?: ($request->cookies->get('refresh_token')
            ?: ($request->header('X-Refresh-Token')
            ?: ($request->input('refresh_token') ?? ''))));

        if ($refreshToken === '' && $request->hasHeader('Cookie')) {
            $cookieHeader = (string) $request->header('Cookie');
            if (preg_match('/(?:^|;\s*)refresh_token=([^;]+)/', $cookieHeader, $matches)) {
                $refreshToken = urldecode($matches[1]);
            }
        }

        return $refreshToken;
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    private function attachRotatedTokenHeadersAndCookies(
        Response $response,
        array $tokens
    ): void {
        $response->headers->set('X-New-Access-Token', (string) $tokens['access_token']);
        if (! empty($tokens['csrf_token'])) {
            $response->headers->set('X-New-CSRF-Token', (string) $tokens['csrf_token']);
        }

        $existingExposed = (string) $response->headers->get('Access-Control-Expose-Headers', '');
        $exposedList = array_filter(array_map('trim', explode(',', $existingExposed)));
        foreach (['X-New-Access-Token', 'X-New-CSRF-Token'] as $h) {
            if (! in_array($h, $exposedList, true)) {
                $exposedList[] = $h;
            }
        }
        $response->headers->set('Access-Control-Expose-Headers', implode(', ', $exposedList));

        $cookie = $this->jwtService->makeRefreshTokenCookie((string) $tokens['refresh_token']);
        $response->headers->setCookie($cookie);

        if (! empty($tokens['csrf_token'])) {
            $response->headers->setCookie($this->jwtService->makeCsrfCookie((string) $tokens['csrf_token']));
        }
    }
}
