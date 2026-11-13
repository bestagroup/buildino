<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('unit_invoices', function (Blueprint $table): void {
            $table->foreignId('start_charge_period_id')->nullable()->constrained('charge_periods')->restrictOnDelete();
            $table->unsignedTinyInteger('period_installment_count')->nullable();
        });
        Schema::create('invoice_period_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_invoice_id')->constrained('unit_invoices')->restrictOnDelete();
            $table->foreignId('charge_period_id')->constrained('charge_periods')->restrictOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->bigInteger('amount');
            $table->foreignId('issued_invoice_id')->nullable()->constrained('unit_invoices')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['unit_invoice_id', 'charge_period_id'], 'invoice_period_source_period_unique');
            $table->unique(['unit_invoice_id', 'sequence'], 'invoice_period_source_sequence_unique');
        });
    }

    public function down(): void
    {
        // Financial schedules must not be removed by a rollback.
    }
};
