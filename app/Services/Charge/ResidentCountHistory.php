<?php

namespace App\Services\Charge;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;

final class ResidentCountHistory
{
    public function record(Unit $unit, int $count, string $effectiveFrom): void
    {
        DB::transaction(function () use ($unit, $count, $effectiveFrom): void {
            Unit::query()->whereKey($unit->getKey())->lockForUpdate()->firstOrFail();
            DB::table('unit_resident_counts')->updateOrInsert(
                ['unit_id' => $unit->getKey(), 'effective_from' => $effectiveFrom],
                ['resident_count' => max(0, $count), 'created_at' => now(), 'updated_at' => now()]
            );
            $current = DB::table('unit_resident_counts')->where('unit_id', $unit->getKey())
                ->whereDate('effective_from', '<=', now()->toDateString())
                ->orderByDesc('effective_from')->value('resident_count');
            if ($current !== null) {
                $unit->forceFill(['resident_count' => (int) $current])->saveQuietly();
            }
        }, 3);
    }
}
