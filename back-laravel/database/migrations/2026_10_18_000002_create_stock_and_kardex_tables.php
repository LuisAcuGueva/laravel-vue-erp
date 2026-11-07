<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('avg_cost', 18, 6)->default(0);
            $table->decimal('min_stock', 18, 4)->default(0);
            $table->decimal('max_stock', 18, 4)->nullable();
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'catalog_item_id']);
            $table->index('catalog_item_id');
        });

        Schema::create('kardex_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignUuid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUuid('item_presentation_id')->nullable()->constrained('item_presentations')->restrictOnDelete();
            $table->foreignUuid('reason_id')->nullable()->constrained('warehouse_reasons')->restrictOnDelete();
            $table->string('direction', 3);           // in | out
            $table->string('movement_type', 20);
            $table->decimal('quantity', 18, 4);       // unidad base, siempre > 0
            $table->decimal('presentation_quantity', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('total_cost', 18, 4);
            $table->decimal('balance_quantity', 18, 4);
            $table->decimal('balance_unit_cost', 18, 6);
            $table->decimal('balance_total_cost', 18, 4);
            $table->string('source_type', 40);
            $table->uuid('source_id');
            $table->string('source_number', 40);
            $table->uuid('transfer_group_id')->nullable();
            $table->foreignUuid('reverses_id')->nullable()->unique();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamp('moved_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['warehouse_id', 'catalog_item_id', 'moved_at']);
            $table->index(['source_type', 'source_id']);
            $table->index('transfer_group_id');
        });

        Schema::table('kardex_movements', function (Blueprint $table) {
            $table->foreign('reverses_id')
                ->references('id')
                ->on('kardex_movements')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex_movements');
        Schema::dropIfExists('stocks');
    }
};
