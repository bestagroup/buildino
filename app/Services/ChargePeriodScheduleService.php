<?php

namespace App\Services;

use App\Enums\ChargePeriodStatus;
use App\Models\Building;
use App\Models\ChargePeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ChargePeriodScheduleService
{
    public function save(Building $building, array $data, User $actor, ?ChargePeriod $period = null): ChargePeriod
    {
        return DB::transaction(function () use ($building, $data, $actor, $period): ChargePeriod {
            Building::query()->whereKey($building->getKey())->lockForUpdate()->firstOrFail();
            if ($period) {
                $period = ChargePeriod::query()->where('building_id', $building->getKey())
                    ->lockForUpdate()->findOrFail($period->getKey());
                if ($period->status !== ChargePeriodStatus::Draft) {
                    throw ValidationException::withMessages(['status' => 'Only a draft charge period can be edited.']);
                }
            }
            $dates = array_merge($period ? [
                'period_start' => $period->period_start->toDateString(),
                'period_end' => $period->period_end->toDateString(),
                'due_date' => $period->due_date->toDateString(),
            ] : [], $data);
            Validator::make($dates, [
                'period_start' => ['required', 'date'],
                'period_end' => ['required', 'date', 'after_or_equal:period_start'],
                'due_date' => ['required', 'date', 'after_or_equal:period_end'],
            ])->validate();
            $overlap = ChargePeriod::query()->where('building_id', $building->getKey())
                ->when($period, fn ($q) => $q->whereKeyNot($period->getKey()))
                ->where('status', '!=', ChargePeriodStatus::Cancelled->value)
                ->whereDate('period_start', '<=', $dates['period_end'])
                ->whereDate('period_end', '>=', $dates['period_start'])->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['period_start' => 'This charge period overlaps an existing period.']);
            }
            if ($period) {
                $period->update($data);

                return $period->refresh();
            }

            return $building->chargePeriods()->create([
                ...$data, 'status' => ChargePeriodStatus::Draft, 'created_by' => $actor->getKey(),
            ]);
        }, 3);
    }
}
