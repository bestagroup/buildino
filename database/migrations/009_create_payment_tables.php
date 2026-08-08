<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_number')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IRR');
            $table->string('method', 30);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['building_id', 'status']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->morphs('payable');
            $table->unsignedBigInteger('amount');
            $table->timestamps();
            $table->index(['payment_id', 'payable_type', 'payable_id'], 'payment_allocation_idx');
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('gateway')->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('authority')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->string('tracking_code')->nullable();
            $table->string('reference_number')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'authority']);
            $table->unique(['gateway', 'gateway_transaction_id']);
        });

        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number')->unique();
            $table->foreignId('file_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
