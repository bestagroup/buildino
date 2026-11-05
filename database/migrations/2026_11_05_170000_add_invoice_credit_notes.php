<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->bigInteger('unit_amount')->change();
            $table->bigInteger('total_amount')->change();
        });
        Schema::table('unit_invoices', function (Blueprint $table): void {
            $table->bigInteger('subtotal')->default(0)->change();
            $table->bigInteger('total_amount')->default(0)->change();
            $table->unsignedBigInteger('credit_applied_amount')->default(0);
            $table->unsignedBigInteger('credit_remaining_amount')->default(0);
        });
        Schema::create('invoice_credit_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_invoice_id')->constrained('unit_invoices')->restrictOnDelete();
            $table->foreignId('debit_invoice_id')->constrained('unit_invoices')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();
            $table->unique(['credit_invoice_id', 'debit_invoice_id'], 'invoice_credit_pair_unique');
        });
    }

    public function down(): void
    {
        // Financial credit history cannot be silently discarded or converted to unsigned amounts.
        if (DB::table('invoice_credit_allocations')->exists()
            || DB::table('invoice_items')->where('unit_amount', '<', 0)->exists()) {
            throw new RuntimeException('Cannot roll back while credit notes or allocations exist.');
        }
        Schema::dropIfExists('invoice_credit_allocations');
        Schema::table('unit_invoices', function (Blueprint $table): void {
            $table->dropColumn(['credit_applied_amount', 'credit_remaining_amount']);
            $table->unsignedBigInteger('subtotal')->default(0)->change();
            $table->unsignedBigInteger('total_amount')->default(0)->change();
        });
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_amount')->change();
            $table->unsignedBigInteger('total_amount')->change();
        });
    }
};
