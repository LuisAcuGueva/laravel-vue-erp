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
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('kind', 10);
            $table->foreignUuid('provider_id')->nullable()->constrained('providers')->restrictOnDelete();
            $table->string('counterparty_name', 150)->nullable();
            $table->string('document_type', 15);
            $table->string('series', 10)->nullable();
            $table->string('document_number', 20);
            $table->date('issue_date');
            $table->date('due_date');
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->boolean('prices_include_tax')->default(true);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('taxable_base', 18, 2)->default(0);
            $table->decimal('exempt_base', 18, 2)->default(0);
            $table->decimal('unaffected_base', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance', 18, 2);
            $table->string('status', 16)->default('pending');
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignUuid('concept_id')->nullable()->constrained('finance_concepts')->restrictOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'status']);
            $table->index(['branch_id', 'issue_date']);
        });

        // Un documento del proveedor no se registra dos veces mientras la factura anterior siga vigente.
        DB::statement("CREATE UNIQUE INDEX supplier_invoices_doc_unique ON supplier_invoices (provider_id, document_type, COALESCE(series, ''), document_number) WHERE status <> 'cancelled' AND provider_id IS NOT NULL");

        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->unsignedSmallInteger('line');
            $table->string('description', 255);
            $table->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
            $table->foreignUuid('purchase_receipt_item_id')->nullable()->constrained('purchase_receipt_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->string('tax_type', 20)->default('taxable');
            $table->decimal('line_subtotal', 18, 2);
            $table->decimal('line_tax', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
        });

        Schema::create('supplier_invoice_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->foreignUuid('purchase_receipt_id')->constrained('purchase_receipts')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['supplier_invoice_id', 'purchase_receipt_id']);
            $table->index('purchase_receipt_id');
        });

        Schema::create('payable_installments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->foreignUuid('provider_id')->nullable()->constrained('providers')->restrictOnDelete();
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance', 18, 2);
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->index(['provider_id', 'status', 'due_date']);
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('provider_id')->nullable()->constrained('providers')->restrictOnDelete();
            $table->string('counterparty_name', 150)->nullable();
            $table->date('payment_date');
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 12, 4)->default(1);
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

            $table->index(['provider_id', 'payment_date']);
            $table->index(['branch_id', 'payment_date']);
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
            $table->foreignUuid('payable_installment_id')->constrained('payable_installments')->restrictOnDelete();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->index('payable_installment_id');
            $table->index('supplier_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('payable_installments');
        Schema::dropIfExists('supplier_invoice_receipts');
        Schema::dropIfExists('supplier_invoice_items');
        Schema::dropIfExists('supplier_invoices');
    }
};
