<?php

declare(strict_types=1);

use App\Support\Database\SoftDeleteUniqueIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('code', 10);
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('branch_id');
        });

        SoftDeleteUniqueIndex::make('boxes', ['code']);

        Schema::create('box_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('box_id')->constrained('boxes')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['box_id', 'role_id']);
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->foreignUuid('box_id')->constrained('boxes')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('status', 10)->default('open');
            $table->foreignUuid('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('opening_amount', 18, 2)->default(0);
            $table->string('opening_notes', 500)->nullable();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 18, 2)->nullable();
            $table->decimal('counted_cash', 18, 2)->nullable();
            $table->decimal('difference', 18, 2)->nullable();
            $table->string('closing_notes', 1000)->nullable();
            $table->string('review_status', 10)->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_notes', 1000)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['box_id', 'opened_at']);
        });

        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_open_per_box ON cash_sessions (box_id) WHERE status = 'open'");

        Schema::create('cash_count_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cash_session_id')->constrained('cash_sessions')->cascadeOnDelete();
            $table->decimal('denomination', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 18, 2);
            $table->timestamps();

            $table->index(['cash_session_id', 'denomination']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignUuid('box_id')->constrained('boxes')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('direction', 3);
            $table->foreignUuid('concept_id')->constrained('finance_concepts')->restrictOnDelete();
            $table->foreignUuid('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->boolean('affects_cash')->default(true);
            $table->decimal('amount', 18, 2);
            $table->string('reference', 60)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('counterparty_name', 150)->nullable();
            $table->string('source_type', 40)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('source_number', 40)->nullable();
            $table->foreignUuid('reverses_id')->nullable()->unique();
            $table->boolean('is_manual')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cash_session_id', 'moved_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreign('reverses_id')
                ->references('id')
                ->on('cash_movements')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_count_details');
        DB::statement('DROP INDEX IF EXISTS cash_sessions_one_open_per_box');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('box_roles');
        Schema::dropIfExists('boxes');
    }
};
