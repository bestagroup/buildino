<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_service_financial_settings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('building_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            /*
             * Basis points:
             * 1000 = 10.00%
             */
            $table->unsignedInteger('platform_commission_bps')
                ->default(1000);

            $table->boolean('allow_user_wallet')
                ->default(true);

            $table->boolean('allow_unit_wallet')
                ->default(true);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();
        });

        Schema::create('platform_wallet_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('code', 80);
            $table->string('title');
            $table->string('currency', 3)->default('IRR');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['code', 'currency'],
                'platform_wallet_account_uq'
            );
        });

        Schema::create('service_request_quotes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('service_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('provider_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount');

            $table->unsignedInteger('commission_rate_bps');

            $table->unsignedBigInteger('commission_amount');

            $table->unsignedBigInteger('provider_amount');

            $table->string('status', 20)
                ->default('pending')
                ->index();

            $table->text('notes')->nullable();

            $table->timestamp('valid_until')->nullable();

            $table->foreignId('accepted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();

            $table->index(
                ['service_request_id', 'status'],
                'service_quote_request_status_idx'
            );
        });

        Schema::create('service_request_wallet_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('service_request_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('service_request_quote_id')
                ->unique()
                ->constrained('service_request_quotes')
                ->restrictOnDelete();

            $table->foreignId('source_wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignId('provider_wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignId('platform_wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->string('payer_source', 30);

            $table->unsignedBigInteger('amount');

            $table->unsignedBigInteger('provider_amount');

            $table->unsignedBigInteger('commission_amount');

            $table->string('status', 20)
                ->default('locked')
                ->index();

            $table->foreignId('provider_transfer_id')
                ->nullable()
                ->unique()
                ->constrained('wallet_transfers')
                ->nullOnDelete();

            $table->foreignId('commission_transfer_id')
                ->nullable()
                ->unique()
                ->constrained('wallet_transfers')
                ->nullOnDelete();

            $table->timestamp('locked_at');

            $table->timestamp('settled_at')->nullable();

            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            $table->index(
                ['service_request_id', 'status'],
                'service_payment_request_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_wallet_payments');
        Schema::dropIfExists('service_request_quotes');
        Schema::dropIfExists('platform_wallet_accounts');
        Schema::dropIfExists('building_service_financial_settings');
    }
};
