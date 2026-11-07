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
        Schema::create('currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('code', 3);
            $table->string('name', 40);
            $table->string('symbol', 5);
            $table->string('sunat_code', 5)->nullable();
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::make('currencies', ['code']);

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX currencies_one_base ON currencies (is_base) WHERE is_base = true AND deleted_at IS NULL');
        }

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->date('date');
            $table->decimal('buy_rate', 12, 4);
            $table->decimal('sell_rate', 12, 4);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['currency_id', 'date']);
        });

        Schema::create('banks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('short_name', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::make('banks', ['name']);

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_id')->constrained('banks')->restrictOnDelete();
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('alias', 60);
            $table->string('account_number', 30);
            $table->char('cci', 20)->nullable();
            $table->string('account_type', 20);
            $table->string('holder_name', 150)->nullable();
            $table->decimal('balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::make('bank_accounts', ['bank_id', 'account_number']);

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20);
            $table->string('name', 60);
            $table->string('kind', 20);
            $table->string('settlement', 20);
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->boolean('requires_reference')->default(false);
            $table->string('sunat_code', 5)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::make('payment_methods', ['code']);

        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('type', 20);
            $table->uuid('parent_id')->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('financial_accounts')->restrictOnDelete();
        });
        SoftDeleteUniqueIndex::make('financial_accounts', ['code']);

        Schema::create('finance_concepts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('direction', 10);
            $table->foreignUuid('financial_account_id')->nullable()->constrained('financial_accounts')->restrictOnDelete();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('direction');
        });
        SoftDeleteUniqueIndex::make('finance_concepts', ['code']);
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_concepts');
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
