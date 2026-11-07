<?php

use App\Modules\Auth\Models\RefreshToken;
use App\Modules\Auth\Models\Session;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('auth:prune-tokens', function () {
    // Primero las filas de refresh_tokens individualmente obsoletas (p. ej. una fila ya
    // rotada dentro de una sesión que sigue activa); luego las sesiones completas que ya
    // expiraron o fueron revocadas (su FK cascadea cualquier refresh_token restante).
    $tokensDeleted = RefreshToken::query()
        ->where('expires_at', '<', now())
        ->orWhereNotNull('revoked_at')
        ->delete();

    $sessionsDeleted = Session::query()
        ->where('expires_at', '<', now())
        ->orWhereNotNull('revoked_at')
        ->delete();

    $this->info("Se eliminaron {$tokensDeleted} refresh tokens y {$sessionsDeleted} sesiones expirados o revocados.");
})->purpose('Eliminar refresh tokens y sesiones expirados o revocados de la base de datos');

// Tareas recurrentes programadas
Schedule::command('auth:prune-tokens')->dailyAt('03:00');
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('sales:expire-quotations')->dailyAt('00:10')->withoutOverlapping();
