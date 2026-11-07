<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignUuid('provider_id')->constrained('providers')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->date('receipt_date');
            $table->string('provider_document_type', 10);
            $table->string('provider_document_number', 30)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('status', 12)->default('draft');
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'receipt_date']);
            $table->index('purchase_order_id');
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_receipt_id')->constrained('purchase_receipts')->cascadeOnDelete();
            $table->unsignedSmallInteger('line');
            $table->foreignUuid('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
            $table->boolean('is_service')->default(false);
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('base_unit_cost', 18, 6)->nullable();
            $table->decimal('total_cost', 18, 2);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
    }
};
