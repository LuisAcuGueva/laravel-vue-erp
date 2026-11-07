<?php

declare(strict_types=1);

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
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->date('count_date');
            $table->string('status', 20)->default('counting');
            $table->string('notes', 1000)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX inventory_counts_one_open_per_warehouse ON inventory_counts (warehouse_id) WHERE status = 'counting'");

        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_count_id')->constrained('inventory_counts')->cascadeOnDelete();
            $table->foreignUuid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->decimal('system_quantity', 18, 4)->default(0);
            $table->decimal('counted_quantity', 18, 4)->nullable();
            $table->decimal('closing_system_quantity', 18, 4)->nullable();
            $table->decimal('difference', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('difference_value', 18, 4)->nullable();
            $table->foreignUuid('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->unique(['inventory_count_id', 'catalog_item_id']);
        });

        Schema::create('warehouse_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20);
            $table->string('type', 10);    // in | out | transfer
            $table->string('status', 20)->default('draft');
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('target_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('reason_id')->constrained('warehouse_reasons')->restrictOnDelete();
            $table->foreignUuid('inventory_count_id')->nullable()->constrained('inventory_counts')->restrictOnDelete();
            $table->date('document_date');
            $table->string('reference_document', 60)->nullable();
            $table->string('observation', 1000)->nullable();
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['type', 'number']);
            $table->index(['branch_id', 'status', 'document_date']);
        });

        Schema::create('warehouse_note_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warehouse_note_id')->constrained('warehouse_notes')->cascadeOnDelete();
            $table->unsignedSmallInteger('line');
            $table->foreignUuid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUuid('item_presentation_id')->constrained('item_presentations')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('base_unit_cost', 18, 6)->nullable();
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_note_items');
        Schema::dropIfExists('warehouse_notes');
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_counts');
    }
};
