<?php

namespace Tests\Feature;

use App\Modules\Audit\Models\Activity;
use App\Modules\Auth\Models\RefreshToken;
use App\Modules\Auth\Models\Session;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Auth\CurrentSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'csrf_token',
                'expires_in',
                'token_type',
                'user' => ['id', 'name', 'email'],
                'roles',
                'permissions',
            ])
            ->assertCookie('refresh_token')
            ->assertCookie('csrf_token', $response->json('csrf_token'), false);

        $this->assertEquals(['admin'], $response->json('roles'));
        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_me_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('admin');

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('access_token');

        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $meResponse->assertOk()
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertJsonPath('roles.0', 'admin');
    }

    public function test_user_can_refresh_token_with_valid_csrf(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $refreshToken = $loginResponse->getCookie('refresh_token', false)->getValue();
        $csrfToken = $loginResponse->json('csrf_token');

        $refreshResponse = $this->withHeader('Cookie', 'refresh_token='.$refreshToken)
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson('/api/auth/refresh');

        $refreshResponse->assertOk()
            ->assertJsonStructure([
                'access_token',
                'csrf_token',
                'expires_in',
            ])
            ->assertCookie('refresh_token');

        $newRefreshToken = $refreshResponse->getCookie('refresh_token', false)->getValue();
        $this->assertNotEquals($refreshToken, $newRefreshToken);
    }

    public function test_refresh_token_fails_with_invalid_csrf(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $refreshToken = $loginResponse->getCookie('refresh_token', false)->getValue();

        $refreshResponse = $this->withHeader('Cookie', 'refresh_token='.$refreshToken)
            ->withHeader('X-CSRF-TOKEN', 'invalid-csrf-token')
            ->postJson('/api/auth/refresh');

        $refreshResponse->assertStatus(403);
        $this->assertNull($refreshResponse->getCookie('refresh_token', false)?->getValue());
        $this->assertDatabaseHas('refresh_tokens', ['revoked_at' => null]);
    }

    public function test_refresh_rotates_csrf_cookie_shared_across_tabs(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $csrfCookie = $login->getCookie('csrf_token', false);
        $this->assertNotNull($csrfCookie);
        $this->assertFalse($csrfCookie->isHttpOnly());

        // Pestaña 2 rota la sesión
        $refresh = $this->withHeader('Cookie', 'refresh_token='.$login->getCookie('refresh_token', false)->getValue())
            ->withHeader('X-CSRF-TOKEN', $csrfCookie->getValue())
            ->postJson('/api/auth/refresh');

        $refresh->assertOk()
            ->assertCookie('csrf_token', $refresh->json('csrf_token'), false);

        // Pestaña 1 usa la cookie vigente (token y CSRF nuevos) y puede rotar de nuevo
        $second = $this->withHeader('Cookie', 'refresh_token='.$refresh->getCookie('refresh_token', false)->getValue())
            ->withHeader('X-CSRF-TOKEN', $refresh->getCookie('csrf_token', false)->getValue())
            ->postJson('/api/auth/refresh');

        $second->assertOk();
    }

    public function test_user_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $refreshToken = $loginResponse->getCookie('refresh_token', false)->getValue();

        $logoutResponse = $this->withHeader('Cookie', 'refresh_token='.$refreshToken)
            ->postJson('/api/auth/logout');

        $logoutResponse->assertOk();

        $tokenHash = hash('sha256', $refreshToken);
        $this->assertNotNull(RefreshToken::where('token_hash', $tokenHash)->first()->revoked_at);
    }

    public function test_expired_access_token_is_automatically_renewed_when_valid_refresh_token_and_csrf_are_provided(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('admin');

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $accessToken = $loginResponse->json('access_token');
        $csrfToken = $loginResponse->json('csrf_token');
        $refreshToken = $loginResponse->getCookie('refresh_token', false)->getValue();

        // Avanzar el tiempo 20 minutos para que el access token expire pero el refresh token siga vigente
        $this->travel(20)->minutes();

        $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->withHeader('Cookie', 'refresh_token='.$refreshToken)
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertHeader('X-New-Access-Token')
            ->assertHeader('X-New-CSRF-Token')
            ->assertCookie('refresh_token');

        $newAccessToken = $response->headers->get('X-New-Access-Token');
        $this->assertNotEmpty($newAccessToken);
        $this->assertNotEquals($accessToken, $newAccessToken);

        // La siguiente petición con el nuevo access token funciona sin requerir renovación
        $subsequentResponse = $this->withHeader('Authorization', 'Bearer '.$newAccessToken)
            ->getJson('/api/auth/me');

        $subsequentResponse->assertOk()
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertHeaderMissing('X-New-Access-Token');
    }

    public function test_expired_access_token_fails_when_refresh_token_is_missing(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('admin');

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $accessToken = $loginResponse->json('access_token');

        // Avanzar el tiempo 20 minutos
        $this->travel(20)->minutes();

        // Sin cookie de refresh_token
        $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson('/api/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Token expirado.');
    }

    public function test_expired_access_token_fails_when_csrf_token_is_missing(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('admin');

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $accessToken = $loginResponse->json('access_token');
        $refreshToken = $loginResponse->getCookie('refresh_token', false)->getValue();

        // Avanzar el tiempo 20 minutos
        $this->travel(20)->minutes();

        // Con cookie de refresh_token pero sin X-CSRF-TOKEN
        $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->withHeader('Cookie', 'refresh_token='.$refreshToken)
            ->getJson('/api/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Token expirado.');
    }

    public function test_authenticated_request_populates_current_session_and_audit_context(): void
    {
        $user = User::factory()->create([
            'email' => 'sessiontest@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'sessiontest@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('access_token');
        $session = Session::where('user_id', $user->id)->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk();

        $this->assertTrue(app(CurrentSession::class)->has());
        $this->assertSame($session->id, app(CurrentSession::class)->id());
        $this->assertSame($session->id, app(AuditContext::class)->sessionId());
    }

    public function test_login_records_last_login_at_activity_with_session_id(): void
    {
        $user = User::factory()->create([
            'email' => 'loginaudit@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'loginaudit@example.com',
            'password' => 'password123',
        ])->assertOk();

        $session = Session::where('user_id', $user->id)->firstOrFail();

        /** @var Activity $log */
        $log = Activity::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame($session->id, $log->session_id);
    }
}
