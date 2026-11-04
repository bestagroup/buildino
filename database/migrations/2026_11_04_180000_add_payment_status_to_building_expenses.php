<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('building_expenses', function (Blueprint $table): void {
            // Historical accounting status does not prove payment to the supplier.
            $table->string('payment_status', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('building_expenses', function (Blueprint $table): void {
            $table->dropColumn('payment_status');
        });
    }
};
