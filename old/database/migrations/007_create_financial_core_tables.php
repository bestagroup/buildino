<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['building_id', 'type']);
        });

        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('title');
            $table->string('type', 30);
            $table->string('currency', 3)->default('IRR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['building_id', 'code']);
        });

        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('charge_formulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('calculation_type', 30);
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('charge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_formula_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->unsignedBigInteger('base_amount')->default(0);
            $table->json('configuration')->nullable();
            $table->timestamps();
        });

        Schema::create('charge_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['building_id', 'period_start', 'period_end']);
        });

        Schema::create('charge_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('charge_formula_id')->constrained()->restrictOnDelete();
            $table->integer('base_value')->nullable();
            $table->unsignedBigInteger('calculated_amount');
            $table->json('calculation_snapshot');
            $table->timestamps();
            $table->unique(['charge_period_id', 'unit_id', 'charge_formula_id'], 'charge_calculation_unique');
        });

        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->string('transaction_type', 30);
            $table->dateTime('occurred_at')->index();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('financial_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 10);
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IRR');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['financial_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ledger_entries');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('charge_calculations');
        Schema::dropIfExists('charge_periods');
        Schema::dropIfExists('charge_items');
        Schema::dropIfExists('charge_formulas');
        Schema::dropIfExists('funds');
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('financial_categories');
    }
};
