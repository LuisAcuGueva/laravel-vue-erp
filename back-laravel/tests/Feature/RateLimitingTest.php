<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rate_limiting_blocks_after_five_attempts(): void
    {
        $payload = [
            'email' => 'rate.limit@example.com',
            'password' => 'wrong-password',
        ];

        // 5 intentos permitidos
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/login', $payload);
            $this->assertNotEquals(429, $response->getStatusCode(), "El intento {$i} no debió ser bloqueado.");
        }

        // El 6to intento debe recibir 429 Too Many Requests
        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(429)
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Demasiadas solicitudes. Por favor intente más tarde.')
            ->assertJsonStructure([
                'success',
                'message',
                'retry_after',
            ]);
    }
}
