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
        Schema::create('providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_type', 10);
            $table->string('document_number', 20);
            $table->string('business_name', 200);
            $table->string('trade_name', 200)->nullable();
            $table->string('address')->nullable();
            $table->char('ubigeo', 6)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website', 200)->nullable();
            $table->foreignUuid('default_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->unsignedSmallInteger('credit_days')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        SoftDeleteUniqueIndex::make('providers', ['document_type', 'document_number']);

        Schema::create('provider_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('position', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('provider_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignUuid('bank_id')->constrained('banks')->restrictOnDelete();
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('account_number', 30);
            $table->char('cci', 20)->nullable();
            $table->boolean('is_detraction_account')->default(false);
            $table->timestamps();
            $table->unique(['provider_id', 'bank_id', 'account_number']);
        });

        Schema::create('provider_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignUuid('item_presentation_id')->constrained('item_presentations')->restrictOnDelete();
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('price', 18, 4);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('notes', 500)->nullable();
            $table->decimal('last_purchase_price', 18, 4)->nullable();
            $table->timestamp('last_purchase_at')->nullable();
            $table->timestamps();
            $table->index(['item_presentation_id', 'valid_from']);
            $table->index(['provider_id', 'item_presentation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_prices');
        Schema::dropIfExists('provider_bank_accounts');
        Schema::dropIfExists('provider_contacts');
        Schema::dropIfExists('providers');
    }
};
