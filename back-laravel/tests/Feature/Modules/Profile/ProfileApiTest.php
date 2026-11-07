<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Profile;

use App\Modules\Auth\Models\Session;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Roles\Models\Role;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new RolePermissionSeeder)->run();
    }

    /**
     * @return array{user: User, token_pair: array<string, mixed>}
     */
    private function authenticateUser(?string $rawPassword = null): array
    {
        $user = User::factory()->create([
            'password' => Hash::make($rawPassword ?? 'password123'),
            'status' => User::STATUS_ACTIVE,
        ]);
        $userRole = Role::where('name', 'user')->first() ?? Role::create(['name' => 'user', 'guard_name' => 'api']);
        $user->assignRole($userRole);

        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);
        $this->withHeader('Authorization', 'Bearer '.$pair['access_token']);

        return ['user' => $user, 'token_pair' => $pair];
    }

    public function test_profile_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(401);
    }

    public function test_profile_can_be_retrieved_by_authenticated_user(): void
    {
        $auth = $this->authenticateUser();
        $user = $auth['user'];

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $user->id)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'status',
                    'roles',
                    'created_at',
                ],
            ]);
    }

    public function test_profile_can_be_updated_with_valid_data(): void
    {
        $auth = $this->authenticateUser();
        $user = $auth['user'];

        $response = $this->putJson('/api/v1/profile', [
            'name' => 'Nuevo Nombre',
            'email' => 'nuevo.correo@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Nuevo Nombre')
            ->assertJsonPath('data.email', 'nuevo.correo@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nuevo Nombre',
            'email' => 'nuevo.correo@example.com',
        ]);
    }

    public function test_profile_update_validates_email_uniqueness_excluding_self(): void
    {
        $auth = $this->authenticateUser();
        $user = $auth['user'];

        User::factory()->create([
            'email' => 'ocupado@example.com',
        ]);

        // Intentar usar un correo ya ocupado
        $failResponse = $this->putJson('/api/v1/profile', [
            'name' => 'Cualquiera',
            'email' => 'ocupado@example.com',
        ]);

        $failResponse->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Mantener su propio correo debe ser permitido
        $okResponse = $this->putJson('/api/v1/profile', [
            'name' => 'Nombre Cambiado',
            'email' => $user->email,
        ]);

        $okResponse->assertOk()
            ->assertJsonPath('data.name', 'Nombre Cambiado');
    }

    public function test_password_can_be_updated_with_valid_current_password(): void
    {
        $auth = $this->authenticateUser('claveActual123');
        $user = $auth['user'];
        $initialTokenVersion = $user->token_version;

        $response = $this->putJson('/api/v1/profile/password', [
            'current_password' => 'claveActual123',
            'password' => 'nuevaClave456',
            'password_confirmation' => 'nuevaClave456',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Contraseña actualizada correctamente.')
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'csrf_token',
                    'expires_in',
                    'token_type',
                ],
            ]);

        $freshUser = $user->fresh();
        $this->assertNotNull($freshUser);
        $this->assertTrue(Hash::check('nuevaClave456', $freshUser->password));
        $this->assertGreaterThan($initialTokenVersion, $freshUser->token_version);

        // Verifica que todas las sesiones previas fueron revocadas y solo queda 1 activa
        $this->assertSame(1, Session::where('user_id', $user->id)->whereNull('revoked_at')->count());
    }

    public function test_password_update_fails_with_wrong_current_password(): void
    {
        $this->authenticateUser('claveActual123');

        $response = $this->putJson('/api/v1/profile/password', [
            'current_password' => 'claveErronea999',
            'password' => 'nuevaClave456',
            'password_confirmation' => 'nuevaClave456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_update_validates_confirmation_and_minimum_length(): void
    {
        $this->authenticateUser('claveActual123');

        // Demasiado corta
        $shortResponse = $this->putJson('/api/v1/profile/password', [
            'current_password' => 'claveActual123',
            'password' => '12345',
            'password_confirmation' => '12345',
        ]);
        $shortResponse->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Confirmación no coincide
        $mismatchResponse = $this->putJson('/api/v1/profile/password', [
            'current_password' => 'claveActual123',
            'password' => 'nuevaClave456',
            'password_confirmation' => 'otraCosa789',
        ]);
        $mismatchResponse->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_password_update_invalidates_previous_access_tokens(): void
    {
        $auth = $this->authenticateUser('claveActual123');
        $oldAccessToken = $auth['token_pair']['access_token'];

        $updateResponse = $this->putJson('/api/v1/profile/password', [
            'current_password' => 'claveActual123',
            'password' => 'nuevaClave456',
            'password_confirmation' => 'nuevaClave456',
        ]);
        $updateResponse->assertOk();

        // Intentar usar el token previo antiguo debe ser rechazado por versión inválida
        $rejectedResponse = $this->withHeader('Authorization', 'Bearer '.$oldAccessToken)
            ->getJson('/api/v1/profile');

        $rejectedResponse->assertStatus(401)
            ->assertJsonPath('message', 'Token revocado o versión no válida.');
    }

    public function test_active_sessions_can_be_listed_with_current_flag(): void
    {
        $auth = $this->authenticateUser();
        $user = $auth['user'];

        // Crear una segunda sesión adicional usando el servicio
        $jwtService = app(JwtService::class);
        $jwtService->createTokenPair($user, '192.168.1.100', 'Mozilla/5.0 (iPhone; CPU OS 17_0 like Mac OS X)');

        $response = $this->getJson('/api/v1/profile/sessions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        /** @var array<int, array<string, mixed>> $data */
        $data = $response->json('data');
        $currentMatches = array_filter($data, fn ($s) => $s['is_current'] === true);
        $otherMatches = array_filter($data, fn ($s) => $s['is_current'] === false);

        $this->assertCount(1, $currentMatches);
        $this->assertCount(1, $otherMatches);

        // Verificar estructura: contiene device_type, browser, platform, last_used_at, NO contiene user_agent ni token_hash
        $firstSession = $data[0];
        $this->assertArrayHasKey('id', $firstSession);
        $this->assertArrayHasKey('ip_address', $firstSession);
        $this->assertArrayHasKey('device_type', $firstSession);
        $this->assertArrayHasKey('browser', $firstSession);
        $this->assertArrayHasKey('platform', $firstSession);
        $this->assertArrayHasKey('created_at', $firstSession);
        $this->assertArrayHasKey('last_used_at', $firstSession);
        $this->assertArrayHasKey('is_current', $firstSession);
        $this->assertArrayNotHasKey('user_agent', $firstSession);
        $this->assertArrayNotHasKey('token_hash', $firstSession);
    }

    public function test_other_sessions_can_be_revoked_preserving_current_session(): void
    {
        $auth = $this->authenticateUser();
        $user = $auth['user'];
        $currentSessionId = $auth['token_pair']['session_id'];

        // Crear una sesión secundaria
        $jwtService = app(JwtService::class);
        $secondaryPair = $jwtService->createTokenPair($user, '10.0.0.50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $secondarySessionId = $secondaryPair['session_id'];

        $response = $this->deleteJson('/api/v1/profile/sessions/others');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.revoked_sessions_count', 1);

        // La sesión secundaria debe estar revocada
        $this->assertTrue(Session::findOrFail($secondarySessionId)->isRevoked());

        // La sesión actual debe seguir activa
        $this->assertTrue(Session::findOrFail($currentSessionId)->isActive());
    }
}
