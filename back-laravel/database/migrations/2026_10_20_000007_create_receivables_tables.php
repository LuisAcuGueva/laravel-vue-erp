<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivable_installments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance', 18, 2);
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->index(['client_id', 'status', 'due_date']);
            $table->index('sale_id');
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->date('payment_date');
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignUuid('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('reference', 60)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('status', 12)->default('completed');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['client_id', 'payment_date']);
            $table->index(['branch_id', 'payment_date']);
        });

        Schema::create('collection_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->foreignUuid('receivable_installment_id')->constrained('receivable_installments')->restrictOnDelete();
            $table->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->index('sale_id');
            $table->index('receivable_installment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_allocations');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('receivable_installments');
    }
};
