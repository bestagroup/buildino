<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('building_expenses', function (Blueprint $table): void {
            $table->foreignId('start_charge_period_id')->nullable()->constrained('charge_periods')->restrictOnDelete();
            $table->unsignedTinyInteger('period_installment_count')->nullable();
            $table->string('distribution_mode', 20)->default('formula');
            $table->json('target_unit_ids')->nullable();
            $table->json('items')->nullable();
            $table->bigInteger('amount')->unsigned(false)->change();
        });
        Schema::table('charge_expense_allocations', fn (Blueprint $table) => $table->bigInteger('allocated_amount')->unsigned(false)->change());
        Schema::create('expense_period_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_expense_id')->constrained()->restrictOnDelete();
            $table->foreignId('charge_period_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->bigInteger('amount');
            $table->timestamps();
            $table->unique(['building_expense_id', 'charge_period_id'], 'expense_period_unique');
            $table->unique(['building_expense_id', 'sequence'], 'expense_sequence_unique');
        });
    }

    // Financial schedules must not be silently destroyed by a rollback.
    public function down(): void {}
};
