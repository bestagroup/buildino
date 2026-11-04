<?php

namespace App\Services\Charge;

use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            ->orderBy('starts_at')->first();

        if (! $occupied) {
            return 0;
        }

        // Preserve whole-period charging: count on the first occupied day.
        // Time-prorated billing is a separate, explicit product policy.
        $date = max($start->toDateString(), $occupied->starts_at->toDateString());
        $count = DB::table('unit_resident_counts')
            ->where('unit_id', $unit->getKey())->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')->value('resident_count');
        if ($count === null) {
            throw ValidationException::withMessages([
                'resident_count' => sprintf('تعداد ساکنین واحد %s در تاریخ %s ثبت نشده است؛ ابتدا سابقه معتبر تعداد نفرات را تکمیل کنید.', $unit->unit_number, $date),
            ]);
        }

        return max(0, (int) $count);
    }
}
