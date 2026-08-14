<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_bank_accounts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('bank_name')->nullable();
            $table->string('account_holder_name');
            $table->string('iban', 34);
            $table->string('account_number', 50)->nullable();
            $table->string('card_number', 19)->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['user_id', 'iban'],
                'pba_user_iban_uq'
            );
        });

        Schema::create('provider_payout_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('provider_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignId('provider_bank_account_id');

            $table->foreign(
                'provider_bank_account_id',
                'ppr_bank_account_fk'
            )
                ->references('id')
                ->on('provider_bank_accounts')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->unsignedBigInteger('net_amount');

            $table->string('status', 20)
                ->default('pending')
                ->index();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('wallet_transfer_id')
                ->nullable()
                ->unique()
                ->constrained('wallet_transfers')
                ->nullOnDelete();

            $table->string('bank_reference')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(
                ['provider_user_id', 'status'],
                'ppr_provider_status_idx'
            );

            $table->index(
                ['wallet_id', 'status'],
                'ppr_wallet_status_idx'
            );
        });

        Schema::create('wallet_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();

            $table->timestamp('reconciled_at');

            $table->bigInteger('entry_balance');
            $table->unsignedBigInteger('stored_balance');

            $table->unsignedBigInteger('expected_locked_balance');
            $table->unsignedBigInteger('stored_locked_balance');

            $table->bigInteger('balance_difference');
            $table->bigInteger('lock_difference');

            $table->string('status', 20)->index();

            $table->json('details')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(
                ['wallet_id', 'reconciled_at'],
                'wallet_reconciliation_wallet_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_reconciliations');
        Schema::dropIfExists('provider_payout_requests');
        Schema::dropIfExists('provider_bank_accounts');
    }
};
