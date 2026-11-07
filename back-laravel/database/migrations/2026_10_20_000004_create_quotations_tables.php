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
        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('client_contacts')->nullOnDelete();
            $table->foreignUuid('seller_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->date('issue_date');
            $table->date('valid_until');
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->string('price_type', 10)->default('retail');
            $table->boolean('prices_include_tax')->default(true);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('global_discount_percent', 5, 2)->default(0);
            $table->decimal('taxable_base', 18, 2)->default(0);
            $table->decimal('exempt_base', 18, 2)->default(0);
            $table->decimal('unaffected_base', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('total_base_currency', 18, 2)->default(0);
            $table->string('status', 12)->default('draft');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('delivery_time', 255)->nullable();
            $table->string('payment_terms', 255)->nullable();
            $table->foreignUuid('discount_override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->uuid('converted_sale_id')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'issue_date']);
            $table->index('client_id');
            $table->index('seller_id');
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->unsignedSmallInteger('line');
            $table->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 18, 4);
            $table->decimal('list_price', 18, 4)->nullable();
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('effective_discount_percent', 5, 2)->default(0);
            $table->string('tax_type', 20)->default('taxable');
            $table->decimal('line_subtotal', 18, 2);
            $table->decimal('line_tax', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
