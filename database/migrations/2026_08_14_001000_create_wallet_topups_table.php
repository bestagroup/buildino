<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_topups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('payment_id')
                ->unique()
                ->constrained('payments')
                ->restrictOnDelete();

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            /*
             * Snapshot of the Wallet owner that was selected when
             * the external payment was created.
             */
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');

            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IRR');

            $table->string('status', 20)
                ->default('pending')
                ->index();

            $table->foreignId('wallet_transfer_id')
                ->nullable()
                ->unique()
                ->constrained('wallet_transfers')
                ->nullOnDelete();

            $table->timestamp('credited_at')->nullable();

            /*
             * Auto-collection after a successful top-up is intentionally
             * non-blocking. Its result is persisted here for audit/debug.
             */
            $table->timestamp('retry_attempted_at')->nullable();
            $table->json('retry_summary')->nullable();

            $table->timestamps();

            $table->index(
                ['target_type', 'target_id'],
                'wallet_topup_target_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_topups');
    }
};
