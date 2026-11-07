<?php

declare(strict_types=1);

use App\Support\Database\SoftDeleteUniqueIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('type', 20); // product | service
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $table->foreignUuid('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->string('tax_type', 20)->default('taxable');
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_sellable')->default(true);
            $table->boolean('is_purchasable')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('last_purchase_cost', 18, 6)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
            $table->index('category_id');
        });
        SoftDeleteUniqueIndex::make('catalog_items', ['code']);

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE INDEX catalog_items_name_lower_idx ON catalog_items (lower(name))');
        }

        Schema::create('item_presentations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->foreignUuid('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name', 80);
            $table->decimal('factor', 18, 4)->default(1);
            $table->string('barcode', 50)->nullable();
            $table->boolean('is_base')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('catalog_item_id');
        });
        SoftDeleteUniqueIndex::make('item_presentations', ['barcode']);

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX item_presentations_one_default ON item_presentations (catalog_item_id) WHERE is_default = true AND deleted_at IS NULL');
        }

        Schema::create('item_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_presentation_id')->constrained('item_presentations')->cascadeOnDelete();
            $table->string('price_type', 20); // retail | wholesale
            $table->decimal('price', 18, 4);
            $table->decimal('min_quantity', 18, 4)->default(1);
            $table->timestamps();

            $table->unique(['item_presentation_id', 'price_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_prices');
        Schema::dropIfExists('item_presentations');
        Schema::dropIfExists('catalog_items');
    }
};
