<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignUuid('document_series_id')->constrained('document_series')->restrictOnDelete();
            $table->char('series', 4);
            $table->unsignedInteger('number');
            $table->string('full_number', 13);
            $table->date('issue_date');
            $table->timestamp('issued_at');
            $table->char('reason_code', 2);
            $table->string('reason_description', 500);

            // Snapshot del cliente
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('client_document_type', 10)->nullable();
            $table->string('client_document_number', 20)->nullable();
            $table->string('client_name', 200);
            $table->string('client_address')->nullable();

            // Moneda y tributos
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->decimal('tax_rate', 5, 2);
            $table->boolean('prices_include_tax')->default(true);
            $table->decimal('taxable_base', 18, 2)->default(0);
            $table->decimal('exempt_base', 18, 2)->default(0);
            $table->decimal('unaffected_base', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2);

            // Liquidación del crédito y reembolsos
            $table->decimal('applied_to_balance', 18, 2)->default(0);
            $table->decimal('refunded_amount', 18, 2)->default(0);
            $table->string('settlement_mode', 10);
            $table->foreignUuid('refund_payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
            $table->foreignUuid('refund_cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();

            // Estados
            $table->string('status', 10)->default('issued');
            $table->string('einvoice_status', 16)->default('not_applicable');
            $table->unsignedTinyInteger('einvoice_attempts')->default(0);
            $table->json('einvoice_response')->nullable();
            $table->timestamp('einvoice_sent_at')->nullable();

            // Auditoría y anulación
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['document_series_id', 'number']);
            $table->index(['sale_id', 'status']);
            $table->index(['branch_id', 'issue_date']);
        });

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->unsignedSmallInteger('line');
            $table->foreignUuid('sale_item_id')->nullable()->constrained('sale_items')->restrictOnDelete();
            $table->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4)->nullable();
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->string('tax_type', 20)->default('taxable');
            $table->decimal('line_subtotal', 18, 2);
            $table->decimal('line_tax', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->boolean('return_to_stock')->default(false);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('credit_note_installment_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignUuid('receivable_installment_id')->constrained('receivable_installments')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_installment_adjustments');
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
    }
};
