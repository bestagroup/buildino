<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');

            $table->string('currency', 3)->default('IRR');

            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('locked_balance')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['owner_type', 'owner_id', 'currency'],
                'wallet_owner_cur_uq'
            );

            $table->index(
                ['owner_type', 'owner_id'],
                'wallet_owner_index'
            );
        });

        Schema::create('wallet_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('source_wallet_id')
                ->nullable()
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignId('destination_wallet_id')
                ->nullable()
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);

            $table->string('type', 40)->index();
            $table->string('status', 20)->default('pending')->index();

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->string('idempotency_key', 190)->unique();

            $table->text('description')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index(
                ['reference_type', 'reference_id'],
                'wallet_transfer_ref_idx'
            );
        });

        Schema::create('wallet_entries', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignId('wallet_transfer_id')
                ->constrained('wallet_transfers')
                ->restrictOnDelete();

            $table->string('entry_type', 10);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_after');

            $table->timestamps();

            $table->unique(
                ['wallet_id', 'wallet_transfer_id', 'entry_type'],
                'wallet_entry_unique'
            );
        });

        Schema::create('building_charge_policies', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('building_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            /*
             * fixed:
             *   Every unit pays fixed_monthly_amount.
             *
             * shared_expenses:
             *   Posted building expenses are distributed by category rules.
             *
             * mixed:
             *   Fixed monthly amount + shared expense distribution.
             */
            $table->string('mode', 30);

            $table->unsignedBigInteger('fixed_monthly_amount')->default(0);

            $table->boolean('auto_collect')->default(true);
            $table->boolean('allow_partial')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('building_expense_allocation_rules', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('building_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('financial_category_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * equal   => equal share between active units
             * area    => proportional to unit area
             * persons => proportional to residents overlapping the charge period
             * custom  => weights supplied in configuration.weights
             */
            $table->string('allocation_method', 30);

            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['building_id', 'financial_category_id'],
                'expense_alloc_rule_uq'
            );
        });

        Schema::create('unit_charge_settings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('unit_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            /*
             * unit_wallet => money is collected from the property's wallet.
             * user_wallet => money is collected from payer_user_id personal wallet.
             */
            $table->string('payer_source', 30)->default('unit_wallet');

            $table->foreignId('payer_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('auto_collect')->default(true);
            $table->boolean('allow_partial')->default(true);

            $table->timestamps();
        });

        Schema::create('charge_expense_allocations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('charge_period_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('building_expense_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('unit_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('building_expense_allocation_rule_id');

            $table->foreign(
                'building_expense_allocation_rule_id',
                'cea_rule_fk'
            )
                ->references('id')
                ->on('building_expense_allocation_rules')
                ->restrictOnDelete();

            $table->decimal('base_value', 18, 4)->default(0);
            $table->unsignedBigInteger('allocated_amount');

            $table->json('calculation_snapshot')->nullable();

            $table->timestamps();

            $table->unique(
                ['charge_period_id', 'building_expense_id', 'unit_id'],
                'charge_exp_unit_uq'
            );
        });

        Schema::create('invoice_wallet_settlements', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('unit_invoice_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('wallet_transfer_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('source_wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignId('destination_wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount');

            $table->timestamps();

            $table->index('unit_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_wallet_settlements');
        Schema::dropIfExists('charge_expense_allocations');
        Schema::dropIfExists('unit_charge_settings');
        Schema::dropIfExists('building_expense_allocation_rules');
        Schema::dropIfExists('building_charge_policies');
        Schema::dropIfExists('wallet_entries');
        Schema::dropIfExists('wallet_transfers');
        Schema::dropIfExists('wallets');
    }
};
