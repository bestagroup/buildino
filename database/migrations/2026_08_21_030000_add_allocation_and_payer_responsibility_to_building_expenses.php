<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('building_expenses', function (Blueprint $table): void {
            $table->foreignId('block_id')
                ->nullable()
                ->after('building_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('allocation_method', 30)
                ->default('equal')
                ->after('financial_category_id');
            $table->json('allocation_configuration')
                ->nullable()
                ->after('allocation_method');
            $table->string('payer_responsibility', 30)
                ->default('unit')
                ->after('allocation_configuration');

            $table->index(
                ['building_id', 'block_id', 'expense_date'],
                'building_expenses_scope_date_idx'
            );
        });

        Schema::table('building_expense_allocation_rules', function (Blueprint $table): void {
            $table->string('payer_responsibility', 30)
                ->default('unit')
                ->after('allocation_method');
        });

        Schema::table('charge_expense_allocations', function (Blueprint $table): void {
            $table->string('payer_responsibility', 30)
                ->default('unit')
                ->after('building_expense_allocation_rule_id');
            $table->foreignId('payer_user_id')
                ->nullable()
                ->after('payer_responsibility')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(
                ['charge_period_id', 'payer_user_id'],
                'charge_expense_allocations_payer_idx'
            );
        });

        Schema::table('unit_invoices', function (Blueprint $table): void {
            $table->string('payer_responsibility', 30)
                ->default('unit')
                ->after('charge_period_id');
            $table->foreignId('payer_user_id')
                ->nullable()
                ->after('payer_responsibility')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(
                ['payer_user_id', 'status'],
                'unit_invoices_payer_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('unit_invoices', function (Blueprint $table): void {
            $table->dropIndex('unit_invoices_payer_status_idx');
            $table->dropConstrainedForeignId('payer_user_id');
            $table->dropColumn('payer_responsibility');
        });

        Schema::table('charge_expense_allocations', function (Blueprint $table): void {
            $table->dropIndex('charge_expense_allocations_payer_idx');
            $table->dropConstrainedForeignId('payer_user_id');
            $table->dropColumn('payer_responsibility');
        });

        Schema::table('building_expense_allocation_rules', function (Blueprint $table): void {
            $table->dropColumn('payer_responsibility');
        });

        Schema::table('building_expenses', function (Blueprint $table): void {
            $table->dropIndex('building_expenses_scope_date_idx');
            $table->dropConstrainedForeignId('block_id');
            $table->dropColumn([
                'allocation_method',
                'allocation_configuration',
                'payer_responsibility',
            ]);
        });
    }
};
