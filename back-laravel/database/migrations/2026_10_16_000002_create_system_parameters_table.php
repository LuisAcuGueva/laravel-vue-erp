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
        Schema::create('system_parameters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('group_key', 50);
            $table->string('item_key', 80);
            $table->string('label', 150);
            $table->string('description', 500)->nullable();
            $table->string('value_type', 20);
            $table->json('value')->nullable();
            $table->boolean('is_editable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['group_key', 'item_key']);
            $table->index('group_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_parameters');
    }
};
