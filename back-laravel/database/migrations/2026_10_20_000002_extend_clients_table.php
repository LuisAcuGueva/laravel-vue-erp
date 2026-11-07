<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_type', 10)->default('person')->after('id');
            $table->string('document_type', 10)->nullable()->after('client_type');
            $table->string('document_number', 20)->nullable()->after('document_type');
            $table->string('trade_name', 200)->nullable()->after('name');
            $table->string('representative_name', 150)->nullable();
            $table->string('representative_document', 15)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->char('ubigeo', 6)->nullable();
            $table->string('default_price_type', 10)->default('retail');
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->unsignedSmallInteger('credit_days')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->string('name', 200)->change();
        });

        DB::statement('CREATE UNIQUE INDEX clients_document_unique ON clients (document_type, document_number) WHERE deleted_at IS NULL AND document_number IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX clients_one_system ON clients (is_system) WHERE is_system = true');
        DB::statement('CREATE INDEX clients_name_lower_idx ON clients (lower(name))');

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('position', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
        DB::statement('DROP INDEX IF EXISTS clients_name_lower_idx');
        DB::statement('DROP INDEX IF EXISTS clients_one_system');
        DB::statement('DROP INDEX IF EXISTS clients_document_unique');

        Schema::table('clients', function (Blueprint $table) {
            $table->string('name', 150)->change();
            $table->dropColumn([
                'client_type',
                'document_type',
                'document_number',
                'trade_name',
                'representative_name',
                'representative_document',
                'email',
                'phone',
                'address',
                'ubigeo',
                'default_price_type',
                'credit_limit',
                'credit_days',
                'is_system',
                'is_active',
            ]);
        });
    }
};
