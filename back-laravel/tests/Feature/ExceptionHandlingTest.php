<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Auth\Services\JwtService;
use App\Modules\Users\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_standard_401_json(): void
    {
        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(401)
            ->assertHeader('X-Request-Id')
            ->assertJson([
                'success' => false,
                'message' => 'No autenticado. Token de acceso no proporcionado.',
                'errors' => null,
            ]);
    }

    public function test_forbidden_request_returns_standard_403_json(): void
    {
        $user = User::factory()->create();
        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);

        // El usuario no tiene el permiso permissions.view
        $response = $this->withHeader('Authorization', 'Bearer '.$pair['access_token'])
            ->getJson('/api/v1/permissions');

        $response->assertStatus(403)
            ->assertHeader('X-Request-Id')
            ->assertJson([
                'success' => false,
                'message' => 'No tienes los permisos necesarios para realizar esta acción.',
                'errors' => null,
            ]);
    }

    public function test_validation_exception_returns_standard_422_json(): void
    {
        (new RolePermissionSeeder)->run();

        $user = User::factory()->create();
        $user->givePermissionTo('clients.create');
        $jwtService = app(JwtService::class);
        $pair = $jwtService->createTokenPair($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$pair['access_token'])
            ->postJson('/api/v1/clients', []);

        $response->assertStatus(422)
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Los datos proporcionados no son válidos.')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['name'],
            ]);
    }

    public function test_not_found_exception_returns_standard_404_json(): void
    {
        $response = $this->getJson('/api/v1/non-existent-endpoint');

        $response->assertStatus(404)
            ->assertHeader('X-Request-Id')
            ->assertJson([
                'success' => false,
                'message' => 'El recurso solicitado no existe.',
                'errors' => null,
            ]);
    }

    public function test_security_headers_and_request_id_are_present(): void
    {
        $response = $this->getJson('/api/v1/clients');

        $response->assertHeader('X-Request-Id')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
