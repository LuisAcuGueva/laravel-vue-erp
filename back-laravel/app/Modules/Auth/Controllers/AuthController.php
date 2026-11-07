<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Maintenance\Resources\UserBranchResource;
use App\Modules\Maintenance\Services\UserBranchService;
use App\Modules\Users\Models\User;
use App\Support\Audit\AuditContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        protected JwtService $jwtService,
        protected UserBranchService $userBranchService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            return response()->json([
                'message' => 'Usuario inactivo.',
            ], 401);
        }

        $tokenPair = $this->jwtService->createTokenPair($user, $request->ip(), $request->userAgent());

        // La sesión ya existe (línea anterior): se registra en AuditContext antes de tocar el
        // modelo para que el log automático de `last_login_at` lleve session_id, igual que
        // cualquier otra mutación dentro de la sesión recién creada.
        app(AuditContext::class)->setSessionId($tokenPair['session_id']);

        $user->update(['last_login_at' => now()]);
        $cookie = $this->jwtService->makeRefreshTokenCookie($tokenPair['refresh_token']);
        $csrfCookie = $this->jwtService->makeCsrfCookie($tokenPair['csrf_token']);

        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->toArray() : [];
        $permissions = method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('slug')->values()->toArray() : [];

        return response()->json([
            'access_token' => $tokenPair['access_token'],
            'csrf_token' => $tokenPair['csrf_token'],
            'expires_in' => $tokenPair['expires_in'],
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'roles' => $roles,
            'permissions' => $permissions,
            'branches' => UserBranchResource::collection($this->userBranchService->accessibleBranches($user)),
        ])->withCookie($cookie)->withCookie($csrfCookie);
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $this->extractRefreshToken($request);

        if ($refreshToken === '') {
            return response()->json([
                'message' => 'Token de actualización no proporcionado.',
            ], 401);
        }

        $csrfToken = $request->header('X-CSRF-TOKEN')
            ?? $request->header('X-Csrf-Token')
            ?? $request->input('csrf_token');

        try {
            $newPair = $this->jwtService->rotateRefreshToken($refreshToken, $csrfToken);
            $cookie = $this->jwtService->makeRefreshTokenCookie($newPair['refresh_token']);
            $csrfCookie = $this->jwtService->makeCsrfCookie($newPair['csrf_token']);

            return response()->json([
                'access_token' => $newPair['access_token'],
                'csrf_token' => $newPair['csrf_token'],
                'expires_in' => $newPair['expires_in'],
                'token_type' => 'Bearer',
            ])->withCookie($cookie)->withCookie($csrfCookie);
        } catch (\Throwable $e) {
            // Un CSRF que no coincide no invalida la sesión: otra pestaña pudo haber rotado
            // el token y esta petición solo llegó con un valor desactualizado.
            if ($e->getCode() === 403) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 403);
            }

            return response()->json([
                'message' => $e->getMessage(),
            ], 401)
                ->withCookie($this->jwtService->makeForgetRefreshTokenCookie())
                ->withCookie($this->jwtService->makeForgetCsrfCookie());
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $this->extractRefreshToken($request);

        if ($refreshToken !== '') {
            $this->jwtService->revokeRefreshToken($refreshToken);
        }

        $forgetCookie = $this->jwtService->makeForgetRefreshTokenCookie();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ])->withCookie($forgetCookie)->withCookie($this->jwtService->makeForgetCsrfCookie());
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->toArray() : [];
        $permissions = method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('slug')->values()->toArray() : [];

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'roles' => $roles,
            'permissions' => $permissions,
            'branches' => UserBranchResource::collection($this->userBranchService->accessibleBranches($user)),
        ]);
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
}
