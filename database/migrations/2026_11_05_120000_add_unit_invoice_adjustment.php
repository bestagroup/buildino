<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_invoices', function (Blueprint $table): void {
            $table->bigInteger('adjustment_amount')->default(0);
            $table->text('adjustment_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('unit_invoices', function (Blueprint $table): void {
            $table->dropColumn(['adjustment_amount', 'adjustment_reason']);
        });
    }
};
