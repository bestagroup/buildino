<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', fn (Blueprint $table) => $table->unsignedSmallInteger('storage_count')->nullable());
    }

    public function down(): void
    {
        Schema::table('units', fn (Blueprint $table) => $table->dropColumn('storage_count'));
    }
};
