<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La migración 0001_01_01_000000_create_users_table ya crea una tabla `sessions`:
        // es la tabla de sesiones HTTP de Laravel (SESSION_DRIVER=database, ver config/session.php),
        // con esquema incompatible (id string, payload, last_activity). Esta API es JWT stateless
        // (routes/api.php no activa el grupo de middleware `web`/StartSession), así que esa tabla
        // nunca recibe filas por el flujo normal de la API. Se reemplaza por la tabla de dominio
        // de este spec bajo el mismo nombre, que es la que el producto necesita.
        Schema::dropIfExists('sessions');

        Schema::create('sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->default('unknown');
            $table->string('browser', 50)->nullable();
            $table->string('platform', 50)->nullable();
            $table->timestampTz('last_used_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'revoked_at'], 'idx_sessions_user_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
