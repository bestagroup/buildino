<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('waived_penalty_amount')
                ->default(0)
                ->after('penalty_amount');
        });

        Schema::table('payment_receipts', function (Blueprint $table): void {
            $table->unique('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table): void {
            $table->dropUnique(['payment_id']);
        });

        Schema::table('unit_invoices', function (Blueprint $table): void {
            $table->dropColumn('waived_penalty_amount');
        });
    }
};
