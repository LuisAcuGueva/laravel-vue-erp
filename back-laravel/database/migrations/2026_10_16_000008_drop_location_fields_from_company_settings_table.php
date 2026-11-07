<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 030: la ubicación de la empresa se guarda solo como código `ubigeo`;
 * los nombres de departamento, provincia y distrito se derivan del catálogo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['department', 'province', 'district']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('department', 100)->nullable()->after('address');
            $table->string('province', 100)->nullable()->after('department');
            $table->string('district', 100)->nullable()->after('province');
        });
    }
};
