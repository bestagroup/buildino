<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table
                ->unsignedSmallInteger('resident_count')
                ->default(0)
                ->after('bedrooms');
        });

        $today = now()->toDateString();

        DB::table('units')
            ->select('id')
            ->orderBy('id')
            ->chunkById(
                200,
                function ($units) use ($today): void {
                    foreach ($units as $unit) {
                        $count = DB::table('unit_occupancies')
                            ->where('unit_id', $unit->id)
                            ->where('is_active', true)
                            ->whereNull('deleted_at')
                            ->whereDate('starts_at', '<=', $today)
                            ->where(function ($query) use ($today): void {
                                $query
                                    ->whereNull('ends_at')
                                    ->orWhereDate('ends_at', '>=', $today);
                            })
                            ->distinct()
                            ->count('user_id');

                        DB::table('units')
                            ->where('id', $unit->id)
                            ->update([
                                'resident_count' => $count,
                            ]);
                    }
                }
            );
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('resident_count');
        });
    }
};
