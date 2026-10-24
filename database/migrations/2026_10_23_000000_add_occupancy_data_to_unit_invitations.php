<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_invitations', function (Blueprint $table): void {
            $table->json('occupancy_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('unit_invitations', function (Blueprint $table): void {
            $table->dropColumn('occupancy_data');
        });
    }
};
