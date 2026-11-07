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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('code', 20)->nullable();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('categories')->restrictOnDelete();
        });

        SoftDeleteUniqueIndex::make('categories', ['code']);

        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::make('brands', ['name']);

        Schema::create('unit_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 60);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::make('unit_categories', ['name']);

        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('unit_category_id')->constrained('unit_categories')->restrictOnDelete();
            $table->string('name', 60);
            $table->string('symbol', 10);
            $table->boolean('is_base')->default(false);
            $table->decimal('conversion_factor', 18, 6)->default(1);
            $table->boolean('allow_decimals')->default(true);
            $table->string('sunat_code', 5)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::make('units', ['unit_category_id', 'name']);

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX units_one_base_per_category ON units (unit_category_id) WHERE is_base = true AND deleted_at IS NULL');
        }

        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('code', 10);
            $table->string('name', 120);
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('branch_id');
        });
        SoftDeleteUniqueIndex::make('warehouses', ['code']);

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX warehouses_one_default_per_branch ON warehouses (branch_id) WHERE is_default = true AND deleted_at IS NULL');
        }

        Schema::create('warehouse_reasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('type', 20); // in | out | transfer | adjustment
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('type');
        });
        SoftDeleteUniqueIndex::make('warehouse_reasons', ['code']);
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_reasons');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('units');
        Schema::dropIfExists('unit_categories');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
