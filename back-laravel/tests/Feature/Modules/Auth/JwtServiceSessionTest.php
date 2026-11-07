<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth;

use App\Modules\Auth\Models\RefreshToken;
use App\Modules\Auth\Models\Session;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JwtServiceSessionTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(JwtService::class);
    }

    public function test_create_token_pair_without_session_creates_session_and_refresh_token_with_matching_session_id(): void
    {
        $user = User::factory()->create();

        $sessionCountBefore = Session::count();
        $tokenCountBefore = RefreshToken::count();

        $pair = $this->jwtService->createTokenPair($user, '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0');

        $this->assertSame($sessionCountBefore + 1, Session::count());
        $this->assertSame($tokenCountBefore + 1, RefreshToken::count());

        $this->assertArrayHasKey('session_id', $pair);
        $sessionId = $pair['session_id'];

        /** @var Session $session */
        $session = Session::findOrFail($sessionId);
        $this->assertSame($user->id, $session->user_id);
        $this->assertSame('192.168.1.100', $session->ip_address);
        $this->assertSame('Google Chrome', $session->browser);
        $this->assertSame('Windows', $session->platform);
        $this->assertSame(Session::DEVICE_DESKTOP, $session->device_type);

        /** @var RefreshToken $refreshToken */
        $refreshToken = RefreshToken::where('session_id', $sessionId)->firstOrFail();
        $this->assertSame($user->id, $refreshToken->user_id);
        $this->assertSame(hash('sha256', $pair['refresh_token']), $refreshToken->token_hash);

        // Validar que el token de acceso contiene el claim sid
        $payload = $this->jwtService->validateAccessToken($pair['access_token']);
        $this->assertSame($sessionId, $payload['sid']);
    }

    public function test_three_consecutive_rotations_preserve_session_id_and_do_not_create_new_sessions(): void
    {
        $user = User::factory()->create();
        $pair = $this->jwtService->createTokenPair($user);
        $sessionId = $pair['session_id'];

        $initialSessionCount = Session::count();

        // 1ª rotación
        $pair2 = $this->jwtService->rotateRefreshToken($pair['refresh_token'], $pair['csrf_token']);
        $this->assertSame($sessionId, $pair2['session_id']);
        $this->assertSame($initialSessionCount, Session::count());

        // 2ª rotación
        $pair3 = $this->jwtService->rotateRefreshToken($pair2['refresh_token'], $pair2['csrf_token']);
        $this->assertSame($sessionId, $pair3['session_id']);
        $this->assertSame($initialSessionCount, Session::count());

        // 3ª rotación
        $pair4 = $this->jwtService->rotateRefreshToken($pair3['refresh_token'], $pair3['csrf_token']);
        $this->assertSame($sessionId, $pair4['session_id']);
        $this->assertSame($initialSessionCount, Session::count());

        // Se deben haber acumulado 4 refresh tokens para la misma sesión (1 original + 3 rotados)
        $this->assertSame(4, RefreshToken::where('session_id', $sessionId)->count());
        $this->assertSame(1, RefreshToken::where('session_id', $sessionId)->whereNull('revoked_at')->count());
    }

    public function test_rotating_refresh_token_of_revoked_session_throws_401_even_within_grace_period(): void
    {
        $user = User::factory()->create();
        $pair = $this->jwtService->createTokenPair($user);

        // Rotamos una vez para que el token quede en periodo de gracia
        $pair2 = $this->jwtService->rotateRefreshToken($pair['refresh_token'], $pair['csrf_token']);

        // Revocamos la sesión completa
        $this->jwtService->revokeSession($pair['session_id']);

        // Ahora intentamos rotar con el token que está en periodo de gracia
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(401);

        $this->jwtService->rotateRefreshToken($pair['refresh_token'], $pair['csrf_token']);
    }

    public function test_revoke_other_sessions_keeps_current_and_revokes_the_rest(): void
    {
        $user = User::factory()->create();

        $session1 = $this->jwtService->createTokenPair($user);
        $session2 = $this->jwtService->createTokenPair($user);
        $session3 = $this->jwtService->createTokenPair($user);

        $revokedCount = $this->jwtService->revokeOtherSessions($user->id, $session2['session_id']);

        $this->assertSame(2, $revokedCount);

        // session2 sigue activa
        $this->assertTrue(Session::findOrFail($session2['session_id'])->isActive());

        // session1 y session3 están revocadas
        $this->assertTrue(Session::findOrFail($session1['session_id'])->isRevoked());
        $this->assertTrue(Session::findOrFail($session3['session_id'])->isRevoked());

        // Sus refresh tokens activos también deben estar revocados
        $this->assertSame(0, RefreshToken::where('session_id', $session1['session_id'])->whereNull('revoked_at')->count());
        $this->assertSame(0, RefreshToken::where('session_id', $session3['session_id'])->whereNull('revoked_at')->count());
        $this->assertSame(1, RefreshToken::where('session_id', $session2['session_id'])->whereNull('revoked_at')->count());
    }
}
