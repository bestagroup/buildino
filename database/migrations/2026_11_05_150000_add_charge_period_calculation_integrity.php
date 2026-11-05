<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charge_periods', function (Blueprint $table): void {
            $table->char('calculation_source_hash', 64)->nullable();
            $table->unsignedBigInteger('calculation_revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('charge_periods', function (Blueprint $table): void {
            $table->dropColumn(['calculation_source_hash', 'calculation_revision']);
        });
    }
};
