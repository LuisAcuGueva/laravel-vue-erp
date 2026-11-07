<?php

declare(strict_types=1);

use App\Support\Database\SoftDeleteUniqueIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10);
            $table->string('name', 150);
            $table->string('address')->nullable();
            $table->char('ubigeo', 6)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        SoftDeleteUniqueIndex::make('branches', ['code']);
        SoftDeleteUniqueIndex::make('branches', ['name']);
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
