<?php

declare(strict_types=1);

namespace App\Modules\Profile\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Profile\Requests\UpdatePasswordRequest;
use App\Modules\Profile\Requests\UpdateProfileRequest;
use App\Modules\Profile\Services\ProfileService;
use App\Modules\Users\Models\User;
use App\Modules\Users\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProfileService $profileService,
        protected JwtService $jwtService,
    ) {}

    public function show(Request $request): JsonResponse|UserResource
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->errorResponse('No autenticado.', 401);
        }

        return new UserResource($user->load('roles'));
    }

    public function update(UpdateProfileRequest $request): JsonResponse|UserResource
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->errorResponse('No autenticado.', 401);
        }

        $updated = $this->profileService->updateProfile($user, $request->toAttributes());

        return new UserResource($updated);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->errorResponse('No autenticado.', 401);
        }

        $newTokens = $this->profileService->updatePassword(
            $user,
            (string) $request->validated('password')
        );

        $cookie = $this->jwtService->makeRefreshTokenCookie((string) $newTokens['refresh_token']);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente.',
            'data' => [
                'access_token' => $newTokens['access_token'],
                'csrf_token' => $newTokens['csrf_token'],
                'expires_in' => $newTokens['expires_in'],
                'token_type' => 'Bearer',
            ],
        ])->withCookie($cookie)
            ->withCookie($this->jwtService->makeCsrfCookie((string) $newTokens['csrf_token']))
            ->withHeaders([
                'X-New-Access-Token' => (string) $newTokens['access_token'],
                'X-New-CSRF-Token' => (string) $newTokens['csrf_token'],
            ]);
    }

    public function listSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->errorResponse('No autenticado.', 401);
        }

        $sessionId = (string) $request->attributes->get('session_id', '');
        $sessions = $this->profileService->listSessions(
            $user,
            $sessionId !== '' ? $sessionId : null
        );

        return $this->successResponse(
            data: $sessions,
            message: 'Sesiones obtenidas correctamente.'
        );
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $this->errorResponse('No autenticado.', 401);
        }

        $sessionId = (string) $request->attributes->get('session_id', '');
        $revokedCount = $this->profileService->revokeOtherSessions(
            $user,
            $sessionId !== '' ? $sessionId : null
        );

        return $this->successResponse(
            data: ['revoked_sessions_count' => $revokedCount],
            message: 'Otras sesiones cerradas correctamente.'
        );
    }
}
