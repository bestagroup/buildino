<?php

namespace App\Services\Charge;

use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class PeriodResidentCount
{
    public function count(Unit $unit, CarbonInterface $start, CarbonInterface $end): int
    {
        // Ended relationships still count for their historical periods.
        // Do not use today's is_active flag to determine historical occupancy.
        $occupied = $unit->unitOccupancies()
            ->whereDate('starts_at', '<=', $end->toDateString())
            ->where(function (Builder $query) use ($start): void {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $start->toDateString());
            })
            ->exists();

        return $occupied ? max(0, (int) ($unit->resident_count ?? 0)) : 0;
    }
}
