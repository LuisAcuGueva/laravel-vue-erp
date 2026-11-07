<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 60);
            $table->string('scope', 60)->default('global');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['key', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_sequences');
    }
};
