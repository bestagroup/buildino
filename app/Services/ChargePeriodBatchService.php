<?php

namespace App\Services;

use App\Models\Building;
use App\Models\ChargePeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\CalendarUtils;

final class ChargePeriodBatchService
{
    private const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    public function __construct(private readonly ChargePeriodScheduleService $schedules) {}

    public function preview(Building $building, array $data): array
    {
        return DB::transaction(function () use ($building, $data): array {
            Building::whereKey($building->id)->lockForUpdate()->firstOrFail();
            return $this->validatedPlan($building, $data);
        }, 3);
    }

    public function create(Building $building, array $data, User $actor): \Illuminate\Support\Collection
    {
        return DB::transaction(function () use ($building, $data, $actor) {
            Building::whereKey($building->id)->lockForUpdate()->firstOrFail();
            $plan = $this->validatedPlan($building, $data);
            return collect($plan)->map(fn (array $row) => $this->schedules->save($building, $row, $actor));
        }, 3);
    }

    private function validatedPlan(Building $building, array $data): array
    {
        Validator::make($data, (new \App\Http\Requests\StoreChargePeriodBatchRequest)->rules())->validate();
        $year = (int) $data['year'];
        $months = array_map('intval', $data['months']);
        sort($months);
        $dates = [];
        foreach (['period_start', 'period_end', 'due_date'] as $key) {
            $dates[$key] = CalendarUtils::toJalali(...array_map('intval', explode('-', $data[$key])));
        }
        foreach (['period_start', 'period_end'] as $key) {
            if ($dates[$key][0] !== $year || $dates[$key][1] !== $months[0]) {
                throw ValidationException::withMessages([$key => 'شروع و پایان الگو باید در اولین ماه انتخاب‌شده از سال دوره باشند.']);
            }
        }
        $endAtMonthEnd = $dates['period_end'][2] === CalendarUtils::jalaliMonthLength($year, $months[0]);
        $plan = [];
        foreach ($months as $month) {
            $row = ['title' => 'شارژ '.self::MONTHS[$month - 1].' '.$year];
            foreach ($dates as $key => [$sourceYear, $sourceMonth, $day]) {
                $index = $sourceYear * 12 + $sourceMonth - 1 + $month - $months[0];
                $targetYear = intdiv($index, 12);
                $targetMonth = $index % 12 + 1;
                $length = CalendarUtils::jalaliMonthLength($targetYear, $targetMonth);
                $day = $key === 'period_end' && $endAtMonthEnd ? $length : min($day, $length);
                $row[$key] = vsprintf('%04d-%02d-%02d', CalendarUtils::toGregorian($targetYear, $targetMonth, $day));
            }
            // A month-end due date cannot move before the adjusted period end.
            if ($row['due_date'] < $row['period_end']) $row['due_date'] = $row['period_end'];
            if (ChargePeriod::where('building_id', $building->id)->where('status', '!=', 'cancelled')
                ->whereDate('period_start', '<=', $row['period_end'])->whereDate('period_end', '>=', $row['period_start'])->exists()) {
                throw ValidationException::withMessages(['months' => 'دوره «'.$row['title'].'» با دوره موجود هم‌پوشانی دارد؛ هیچ‌کدام از دوره‌های گروه ثبت نشد.']);
            }
            $plan[] = $row;
        }
        return $plan;
    }
}
