<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La empresa emisora siempre se identifica con RUC: se elimina `tax_id_type` y `tax_id` pasa a `ruc char(11)`.
 * `ruc` es nullable mientras la empresa no esté configurada (antes se guardaba el placeholder '00000000000').
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('tax_id', 20)->nullable()->change();
        });

        // Valores que no son un RUC de 11 dígitos (placeholder, DNI, etc.) quedan sin configurar.
        DB::table('company_settings')
            ->where(fn ($query) => $query
                ->where('tax_id', '00000000000')
                ->orWhereRaw("tax_id !~ '^[0-9]{11}$'"))
            ->update(['tax_id' => null]);

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('tax_id_type');
            $table->renameColumn('tax_id', 'ruc');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->char('ruc', 11)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('ruc', 20)->nullable()->change();
        });

        DB::table('company_settings')->whereNull('ruc')->update(['ruc' => '00000000000']);

        Schema::table('company_settings', function (Blueprint $table) {
            $table->renameColumn('ruc', 'tax_id');
            $table->string('tax_id_type', 10)->default('RUC')->after('trade_name');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('tax_id', 20)->nullable(false)->change();
        });
    }
};
