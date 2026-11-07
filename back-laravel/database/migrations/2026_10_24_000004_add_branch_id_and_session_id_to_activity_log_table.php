<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promueve `branch_id`/`session_id` de claves sueltas dentro de `properties` (JSON) a
     * columnas propias e indexadas, para poder filtrar/auditar a escala sin escanear JSON
     * fila por fila. A partir de este cambio, `App\Modules\Audit\Models\Activity` escribe
     * solo en estas columnas; las filas históricas conservan además la clave en `properties`
     * (json, no jsonb: no se borra sin un cast adicional) — duplicación inofensiva, no se lee.
     */
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name');

        Schema::connection($connection)->table($table, function (Blueprint $blueprint) {
            $blueprint->uuid('branch_id')->nullable()->after('causer_id');
            $blueprint->uuid('session_id')->nullable()->after('branch_id');

            $blueprint->index(['branch_id', 'created_at'], 'idx_activity_log_branch');
            $blueprint->index(['session_id', 'created_at'], 'idx_activity_log_session');
        });

        // Backfill desde filas existentes. Se valida el formato UUID antes del cast para no
        // abortar la migración si alguna fila histórica tuviera un valor no válido.
        $uuidPattern = '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$';

        DB::connection($connection)->statement(
            "UPDATE \"{$table}\" SET branch_id = (properties::jsonb->>'branch_id')::uuid
             WHERE properties::jsonb->>'branch_id' ~ ?",
            [$uuidPattern]
        );

        DB::connection($connection)->statement(
            "UPDATE \"{$table}\" SET session_id = (properties::jsonb->>'session_id')::uuid
             WHERE properties::jsonb->>'session_id' ~ ?",
            [$uuidPattern]
        );
    }

    public function down(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name');

        Schema::connection($connection)->table($table, function (Blueprint $blueprint) {
            $blueprint->dropIndex('idx_activity_log_branch');
            $blueprint->dropIndex('idx_activity_log_session');
            $blueprint->dropColumn(['branch_id', 'session_id']);
        });
    }
};
