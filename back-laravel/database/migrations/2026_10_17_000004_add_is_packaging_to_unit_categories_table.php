<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_categories', function (Blueprint $table) {
            // Categoría de empaque: sus unidades no tienen base ni factor fijo; el factor lo define cada presentación.
            $table->boolean('is_packaging')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('unit_categories', function (Blueprint $table) {
            $table->dropColumn('is_packaging');
        });
    }
};
