<?php

use App\Support\Auth\UserAgentParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->uuid('session_id')->nullable()->after('user_id');
        });

        // Backfill: no existe información histórica para reconstruir con certeza qué filas
        // revocadas pertenecían a la misma sesión lógica (ip_address/user_agent nunca
        // se pensaron como clave de agrupación). Se crea una sesión 1:1 por cada refresh
        // token EXISTENTE (activo o no), preservando ip/user_agent/created_at tal cual.
        // A partir de este punto, la rotación sí preserva session_id correctamente.
        DB::table('refresh_tokens')->orderBy('id')->chunkById(500, function ($tokens) {
            foreach ($tokens as $token) {
                $sessionId = (string) Str::uuid7();

                DB::table('sessions')->insert([
                    'id' => $sessionId,
                    'user_id' => $token->user_id,
                    'ip_address' => $token->ip_address,
                    'user_agent' => $token->user_agent,
                    'device_type' => UserAgentParser::parse($token->user_agent)['device_type'],
                    'browser' => UserAgentParser::parse($token->user_agent)['browser'],
                    'platform' => UserAgentParser::parse($token->user_agent)['platform'],
                    'last_used_at' => $token->created_at,
                    'expires_at' => $token->expires_at,
                    'revoked_at' => $token->revoked_at,
                    'created_at' => $token->created_at,
                    'updated_at' => $token->updated_at,
                ]);

                DB::table('refresh_tokens')->where('id', $token->id)->update(['session_id' => $sessionId]);
            }
        });

        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->uuid('session_id')->nullable(false)->change();
            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('session_id');
        });
    }
};
