<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transfers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('number', 20)->unique();
            $t->string('status', 10)->default('pending');
            $t->foreignUuid('source_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $t->foreignUuid('source_box_id')->constrained('boxes')->restrictOnDelete();
            $t->foreignUuid('source_branch_id')->constrained('branches')->restrictOnDelete();
            $t->foreignUuid('target_box_id')->constrained('boxes')->restrictOnDelete();
            $t->foreignUuid('target_branch_id')->constrained('branches')->restrictOnDelete();
            $t->foreignUuid('target_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $t->decimal('amount', 18, 2);
            $t->string('reason', 255);
            $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('rejected_at')->nullable();
            $t->string('rejection_reason', 500)->nullable();
            $t->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason', 500)->nullable();
            $t->timestamps();

            $t->index(['source_branch_id', 'status']);
            $t->index(['target_branch_id', 'status']);
            $t->index(['source_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transfers');
    }
};
