<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'branch_id']);
        });

        $connection = Schema::getConnection();
        if (in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            $connection->statement('CREATE UNIQUE INDEX user_branches_one_default ON user_branches (user_id) WHERE is_default = true');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_branches');
    }
};
