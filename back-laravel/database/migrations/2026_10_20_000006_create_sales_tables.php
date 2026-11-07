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
        Schema::create('document_series', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $t->foreignUuid('box_id')->nullable()->constrained('boxes')->restrictOnDelete();
            $t->string('document_type', 15);
            $t->char('series', 4);
            $t->unsignedInteger('last_number')->default(0);
            $t->boolean('is_electronic')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        SoftDeleteUniqueIndex::make('document_series', ['document_type', 'series']);

        Schema::create('sales', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $t->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $t->foreignUuid('document_series_id')->constrained('document_series')->restrictOnDelete();
            $t->string('document_type', 15);
            $t->char('series', 4);
            $t->unsignedInteger('number');
            $t->string('full_number', 13);
            $t->date('issue_date');
            $t->timestamp('issued_at');
            $t->date('due_date')->nullable();
            $t->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $t->string('client_document_type', 10)->nullable();
            $t->string('client_document_number', 20)->nullable();
            $t->string('client_name', 200);
            $t->string('client_address')->nullable();
            $t->foreignUuid('seller_id')->nullable()->constrained('employees')->restrictOnDelete();
            $t->foreignUuid('quotation_id')->nullable()->constrained('quotations')->restrictOnDelete();
            $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $t->decimal('exchange_rate', 12, 4)->default(1);
            $t->string('price_type', 10)->default('retail');
            $t->boolean('prices_include_tax')->default(true);
            $t->decimal('tax_rate', 5, 2);
            $t->decimal('global_discount_percent', 5, 2)->default(0);
            $t->decimal('taxable_base', 18, 2)->default(0);
            $t->decimal('exempt_base', 18, 2)->default(0);
            $t->decimal('unaffected_base', 18, 2)->default(0);
            $t->decimal('discount_total', 18, 2)->default(0);
            $t->decimal('tax_total', 18, 2)->default(0);
            $t->decimal('total', 18, 2);
            $t->decimal('total_base_currency', 18, 2);
            $t->decimal('cost_total', 18, 4)->default(0);
            $t->string('payment_condition', 10);
            $t->decimal('paid_amount', 18, 2)->default(0);
            $t->decimal('balance', 18, 2)->default(0);
            $t->string('status', 10)->default('issued');
            $t->string('einvoice_status', 16)->default('not_applicable');
            $t->unsignedTinyInteger('einvoice_attempts')->default(0);
            $t->json('einvoice_response')->nullable();
            $t->timestamp('einvoice_sent_at')->nullable();
            $t->foreignUuid('discount_override_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('notes', 500)->nullable();
            $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason', 500)->nullable();
            $t->timestamps();

            $t->unique(['document_series_id', 'number']);
            $t->index(['branch_id', 'issue_date', 'status']);
            $t->index(['client_id', 'status']);
            $t->index('cash_session_id');
        });

        Schema::create('sale_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
            $t->unsignedSmallInteger('line');
            $t->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $t->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
            $t->string('description', 255);
            $t->string('unit_sunat_code', 5)->nullable();
            $t->decimal('quantity', 18, 4);
            $t->decimal('base_quantity', 18, 4)->nullable();
            $t->decimal('list_price', 18, 4)->nullable();
            $t->decimal('unit_price', 18, 4);
            $t->decimal('discount_percent', 5, 2)->default(0);
            $t->decimal('effective_discount_percent', 5, 2)->default(0);
            $t->string('tax_type', 20)->default('taxable');
            $t->decimal('line_subtotal', 18, 2);
            $t->decimal('line_tax', 18, 2);
            $t->decimal('line_total', 18, 2);
            $t->decimal('unit_cost', 18, 6)->default(0);
            $t->decimal('cost_total', 18, 4)->default(0);
            $t->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
            $t->foreignUuid('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $t->foreignUuid('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $t->decimal('amount', 18, 2);
            $t->decimal('received_amount', 18, 2)->nullable();
            $t->decimal('change_amount', 18, 2)->default(0);
            $t->string('reference', 60)->nullable();
            $t->string('status', 12)->default('completed');
            $t->timestamps();
        });

        Schema::table('quotations', function (Blueprint $t) {
            $t->foreign('converted_sale_id')->references('id')->on('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $t) {
            $t->dropForeign(['converted_sale_id']);
        });

        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('document_series');
    }
};
