<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Auth\Models\RefreshToken;
use App\Modules\Auth\Models\Session;
use App\Modules\Users\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(User $user, bool $revoked = false, bool $expired = false): Session
    {
        return Session::query()->create([
            'user_id' => $user->id,
            'last_used_at' => now(),
            'expires_at' => $expired ? now()->subDay() : now()->addDays(7),
            'revoked_at' => $revoked ? now()->subHours(5) : null,
        ]);
    }

    public function test_auth_prune_tokens_command_deletes_expired_and_revoked_tokens(): void
    {
        $user = User::factory()->create();

        // 1. Sesión y token activos y vigentes (no deben ser eliminados)
        $activeSession = $this->makeSession($user);
        $activeToken = RefreshToken::query()->create([
            'user_id' => $user->id,
            'session_id' => $activeSession->id,
            'token_hash' => hash('sha256', 'active_token'),
            'csrf_token_hash' => hash('sha256', 'csrf_active'),
            'expires_at' => now()->addDays(7),
            'revoked_at' => null,
        ]);

        // 2. Token individualmente expirado dentro de una sesión aún activa (ya rotado;
        //    se elimina el token, la sesión activa permanece)
        $expiredToken = RefreshToken::query()->create([
            'user_id' => $user->id,
            'session_id' => $activeSession->id,
            'token_hash' => hash('sha256', 'expired_token'),
            'csrf_token_hash' => hash('sha256', 'csrf_expired'),
            'expires_at' => now()->subDay(),
            'revoked_at' => null,
        ]);

        // 3. Token individualmente revocado dentro de la misma sesión activa (rotación normal)
        $revokedToken = RefreshToken::query()->create([
            'user_id' => $user->id,
            'session_id' => $activeSession->id,
            'token_hash' => hash('sha256', 'revoked_token'),
            'csrf_token_hash' => hash('sha256', 'csrf_revoked'),
            'expires_at' => now()->addDays(2),
            'revoked_at' => now()->subHours(5),
        ]);

        // 4. Sesión revocada completa (logout) con su token aún no limpiado individualmente
        $revokedSession = $this->makeSession($user, revoked: true);
        $revokedSessionToken = RefreshToken::query()->create([
            'user_id' => $user->id,
            'session_id' => $revokedSession->id,
            'token_hash' => hash('sha256', 'revoked_session_token'),
            'csrf_token_hash' => hash('sha256', 'csrf_revoked_session'),
            'expires_at' => now()->addDays(2),
            'revoked_at' => null,
        ]);

        // Ejecutar comando Artisan. revokedSessionToken se elimina por cascada de FK al borrar
        // revokedSession (paso 2), no lo cuenta el borrado directo de refresh_tokens (paso 1).
        $this->artisan('auth:prune-tokens')
            ->expectsOutputToContain('Se eliminaron 2 refresh tokens y 1 sesiones expirados o revocados.')
            ->assertSuccessful();

        // Aserciones en BD: la sesión activa y su token vigente permanecen
        $this->assertDatabaseHas('sessions', ['id' => $activeSession->id]);
        $this->assertDatabaseHas('refresh_tokens', ['id' => $activeToken->id]);

        // Los tokens individualmente obsoletos dentro de la sesión activa se eliminan
        $this->assertDatabaseMissing('refresh_tokens', ['id' => $expiredToken->id]);
        $this->assertDatabaseMissing('refresh_tokens', ['id' => $revokedToken->id]);

        // La sesión revocada y su token restante se eliminan (cascada por FK)
        $this->assertDatabaseMissing('sessions', ['id' => $revokedSession->id]);
        $this->assertDatabaseMissing('refresh_tokens', ['id' => $revokedSessionToken->id]);
    }

    public function test_scheduled_tasks_are_properly_registered(): void
    {
        $schedule = app(Schedule::class);

        $events = collect($schedule->events());

        // Verificar que existe el comando auth:prune-tokens programado
        $hasPruneTokens = $events->contains(function ($event) {
            return str_contains($event->command ?? '', 'auth:prune-tokens');
        });

        // Verificar que existe la poda de cola fallida programada
        $hasPruneFailedJobs = $events->contains(function ($event) {
            return str_contains($event->command ?? '', 'queue:prune-failed');
        });

        $this->assertTrue($hasPruneTokens, 'La tarea auth:prune-tokens no está registrada en el scheduler.');
        $this->assertTrue($hasPruneFailedJobs, 'La tarea queue:prune-failed no está registrada en el scheduler.');
    }
}
