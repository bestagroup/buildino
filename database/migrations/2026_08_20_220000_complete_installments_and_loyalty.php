<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_rules', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('event_type');
        });

        Schema::table('loyalty_transactions', function (Blueprint $table): void {
            $table->foreignId('loyalty_rule_id')
                ->nullable()
                ->after('loyalty_account_id')
                ->constrained('loyalty_rules')
                ->nullOnDelete();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->integer('balance_after')->default(0);
            $table->unsignedInteger('remaining_points')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('reversed_transaction_id')
                ->nullable()
                ->constrained('loyalty_transactions')
                ->nullOnDelete();
        });

        Schema::create('loyalty_transaction_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spend_transaction_id')
                ->constrained('loyalty_transactions')
                ->cascadeOnDelete();
            $table->foreignId('earn_transaction_id')
                ->constrained('loyalty_transactions')
                ->restrictOnDelete();
            $table->unsignedInteger('points');
            $table->timestamps();
            $table->unique(
                ['spend_transaction_id', 'earn_transaction_id'],
                'loyalty_allocation_unique'
            );
        });

        Schema::table('loyalty_reward_claims', function (Blueprint $table): void {
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->foreignId('loyalty_transaction_id')
                ->nullable()
                ->constrained('loyalty_transactions')
                ->nullOnDelete();
        });

        Schema::table('invoice_installments', function (Blueprint $table): void {
            $table->unsignedBigInteger('penalty_amount')->default(0);
            $table->unsignedBigInteger('waived_amount')->default(0);
            $table->json('metadata')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_installments', function (Blueprint $table): void {
            $table->dropColumn([
                'penalty_amount',
                'waived_amount',
                'metadata',
            ]);
        });

        Schema::table('loyalty_reward_claims', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('loyalty_transaction_id');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });

        Schema::dropIfExists('loyalty_transaction_allocations');

        Schema::table('loyalty_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversed_transaction_id');
            $table->dropConstrainedForeignId('loyalty_rule_id');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'idempotency_key',
                'balance_after',
                'remaining_points',
                'metadata',
            ]);
        });

        Schema::table('loyalty_rules', function (Blueprint $table): void {
            $table->dropColumn('version');
        });
    }
};
