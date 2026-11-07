<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 10)->default('open');
            $table->date('opened_at');
            $table->decimal('opening_balance', 18, 2);
            $table->date('closed_at')->nullable();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('expected_balance', 18, 2)->nullable();
            $table->decimal('statement_balance', 18, 2)->nullable();
            $table->decimal('difference', 18, 2)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->unique(['bank_account_id', 'number']);
        });

        DB::statement("CREATE UNIQUE INDEX bank_periods_one_open ON bank_periods (bank_account_id) WHERE status = 'open'");

        Schema::create('bank_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->string('type', 12);
            $table->string('status', 10)->default('completed');
            $table->foreignUuid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignUuid('target_bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->decimal('target_amount', 18, 2)->nullable();
            $table->decimal('exchange_rate', 12, 4)->nullable();
            $table->string('operation_number', 40)->nullable();
            $table->date('operation_date');
            $table->string('notes', 500)->nullable();
            $table->string('voucher_path')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['bank_account_id', 'operation_date']);
        });

        Schema::create('bank_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignUuid('bank_period_id')->constrained('bank_periods')->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('direction', 3);
            $table->foreignUuid('concept_id')->constrained('finance_concepts')->restrictOnDelete();
            $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->string('operation_number', 40)->nullable();
            $table->date('movement_date');
            $table->string('description', 255)->nullable();
            $table->string('counterparty_name', 150)->nullable();
            $table->string('voucher_path')->nullable();
            $table->string('source_type', 40)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('source_number', 40)->nullable();
            $table->foreignUuid('reverses_id')->nullable()->unique();
            $table->boolean('is_manual')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['bank_account_id', 'movement_date']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('bank_movements', function (Blueprint $table) {
            $table->foreign('reverses_id')
                ->references('id')
                ->on('bank_movements')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_movements');
        Schema::dropIfExists('bank_operations');
        Schema::dropIfExists('bank_periods');
    }
};
