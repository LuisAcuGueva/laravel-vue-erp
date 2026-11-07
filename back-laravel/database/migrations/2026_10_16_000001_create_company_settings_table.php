<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Garantiza un único registro de empresa: el índice único rechaza una segunda fila.
            $table->boolean('singleton')->default(true)->unique();
            $table->string('legal_name', 200);
            $table->string('trade_name', 200)->nullable();
            $table->string('tax_id_type', 10)->default('RUC');
            $table->string('tax_id', 20);
            $table->string('address')->nullable();
            $table->char('ubigeo', 6)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 200)->nullable();
            $table->string('logo_path')->nullable();
            $table->char('default_currency_code', 3)->default('PEN');
            $table->decimal('default_tax_rate', 5, 2)->default(18.00);
            $table->boolean('prices_include_tax')->default(true);
            $table->text('terms_conditions')->nullable();
            $table->text('warranty_policy')->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
