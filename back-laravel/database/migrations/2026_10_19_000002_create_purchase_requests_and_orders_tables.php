<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('number', 20)->unique();
            $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $t->foreignUuid('area_id')->constrained('areas')->restrictOnDelete();
            $t->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $t->date('required_date');
            $t->string('priority', 10)->default('normal');
            $t->string('status', 20)->default('draft');
            $t->string('notes', 1000)->nullable();
            $t->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('rejected_at')->nullable();
            $t->string('rejection_reason', 500)->nullable();
            $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason', 500)->nullable();
            $t->timestamps();
            $t->index(['branch_id', 'status']);
        });

        Schema::create('purchase_request_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $t->unsignedSmallInteger('line');
            $t->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $t->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
            $t->string('description', 255)->nullable();
            $t->decimal('quantity', 18, 4);
            $t->decimal('ordered_quantity', 18, 4)->default(0);
            $t->string('notes', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('number', 20)->unique();
            $t->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $t->foreignUuid('provider_id')->constrained('providers')->restrictOnDelete();
            $t->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $t->date('order_date');
            $t->date('expected_date')->nullable();
            $t->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $t->decimal('exchange_rate', 12, 4)->default(1);
            $t->string('payment_condition', 10);
            $t->unsignedSmallInteger('credit_days')->default(0);
            $t->boolean('prices_include_tax')->default(false);
            $t->decimal('tax_rate', 5, 2);
            $t->decimal('taxable_base', 18, 2)->default(0);
            $t->decimal('exempt_base', 18, 2)->default(0);
            $t->decimal('unaffected_base', 18, 2)->default(0);
            $t->decimal('discount_total', 18, 2)->default(0);
            $t->decimal('tax_total', 18, 2)->default(0);
            $t->decimal('total', 18, 2)->default(0);
            $t->decimal('total_base_currency', 18, 2)->default(0);
            $t->string('status', 20)->default('draft');
            $t->text('notes')->nullable();
            $t->text('terms')->nullable();
            $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('rejected_at')->nullable();
            $t->string('rejection_reason', 500)->nullable();
            $t->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('closed_at')->nullable();
            $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason', 500)->nullable();
            $t->timestamps();
            $t->index(['branch_id', 'status', 'order_date']);
            $t->index('provider_id');
        });

        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $t->unsignedSmallInteger('line');
            $t->foreignUuid('purchase_request_item_id')->nullable()->constrained('purchase_request_items')->restrictOnDelete();
            $t->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $t->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
            $t->string('description', 255)->nullable();
            $t->boolean('is_service')->default(false);
            $t->decimal('quantity', 18, 4);
            $t->decimal('received_quantity', 18, 4)->default(0);
            $t->decimal('unit_price', 18, 4);
            $t->decimal('discount_percent', 5, 2)->default(0);
            $t->string('tax_type', 20)->default('taxable');
            $t->decimal('line_subtotal', 18, 2);
            $t->decimal('line_tax', 18, 2);
            $t->decimal('line_total', 18, 2);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
    }
};
