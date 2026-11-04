<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_resident_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->unsignedSmallInteger('resident_count');
            $table->timestamps();
            $table->unique(['unit_id', 'effective_from']);
        });
        // Only today's value is known. Never manufacture historical counts.
        DB::table('units')->orderBy('id')->chunkById(500, function ($units): void {
            DB::table('unit_resident_counts')->insert($units->map(fn ($unit) => [
                'unit_id' => $unit->id, 'effective_from' => now()->toDateString(),
                'resident_count' => max(0, (int) $unit->resident_count),
                'created_at' => now(), 'updated_at' => now(),
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_resident_counts');
    }
};
