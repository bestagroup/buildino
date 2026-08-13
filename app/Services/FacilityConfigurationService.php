<?php

namespace App\Services;

use App\Models\BuildingFacility;
use App\Models\FacilityBlackout;
use App\Models\FacilityReservationRule;
use App\Models\FacilitySchedule;
use App\Models\FacilityTimeSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FacilityConfigurationService
{
    public function createSchedule(BuildingFacility $facility, array $data): FacilitySchedule
    {
        return DB::transaction(function () use ($facility, $data): FacilitySchedule {
            $this->assertTimeOrder($data['start_time'], $data['end_time'], 'end_time');
            $this->assertScheduleDoesNotOverlap($facility, $data);

            return $facility->facilitySchedules()->create($data);
        });
    }

    public function updateSchedule(FacilitySchedule $schedule, array $data): FacilitySchedule
    {
        return DB::transaction(function () use ($schedule, $data): FacilitySchedule {
            $merged = [
                'day_of_week' => $data['day_of_week'] ?? $schedule->day_of_week,
                'start_time' => $data['start_time'] ?? $schedule->start_time,
                'end_time' => $data['end_time'] ?? $schedule->end_time,
                'is_active' => $data['is_active'] ?? $schedule->is_active,
            ];

            $this->assertTimeOrder($merged['start_time'], $merged['end_time'], 'end_time');
            $this->assertScheduleDoesNotOverlap($schedule->buildingFacility, $merged, $schedule->getKey());

            $schedule->update($data);
            return $schedule->refresh();
        });
    }

    public function createTimeSlot(FacilitySchedule $schedule, array $data): FacilityTimeSlot
    {
        return DB::transaction(function () use ($schedule, $data): FacilityTimeSlot {
            $this->assertTimeOrder($data['start_time'], $data['end_time'], 'end_time');
            $this->assertTimeSlotInsideSchedule($schedule, $data);
            $this->assertTimeSlotDoesNotOverlap($schedule, $data);

            return $schedule->facilityTimeSlots()->create($data);
        });
    }

    public function updateTimeSlot(FacilityTimeSlot $timeSlot, array $data): FacilityTimeSlot
    {
        return DB::transaction(function () use ($timeSlot, $data): FacilityTimeSlot {
            $timeSlot->loadMissing('facilitySchedule');

            $merged = [
                'start_time' => $data['start_time'] ?? $timeSlot->start_time,
                'end_time' => $data['end_time'] ?? $timeSlot->end_time,
                'capacity' => array_key_exists('capacity', $data) ? $data['capacity'] : $timeSlot->capacity,
                'price' => $data['price'] ?? $timeSlot->price,
                'is_active' => $data['is_active'] ?? $timeSlot->is_active,
            ];

            $this->assertTimeOrder($merged['start_time'], $merged['end_time'], 'end_time');
            $this->assertTimeSlotInsideSchedule($timeSlot->facilitySchedule, $merged);
            $this->assertTimeSlotDoesNotOverlap($timeSlot->facilitySchedule, $merged, $timeSlot->getKey());

            $timeSlot->update($data);
            return $timeSlot->refresh();
        });
    }

    public function upsertRule(BuildingFacility $facility, array $data): FacilityReservationRule
    {
        return DB::transaction(fn (): FacilityReservationRule => FacilityReservationRule::query()->updateOrCreate(
            ['building_facility_id' => $facility->getKey()],
            $data
        ));
    }

    public function createBlackout(BuildingFacility $facility, User $actor, array $data): FacilityBlackout
    {
        return DB::transaction(function () use ($facility, $actor, $data): FacilityBlackout {
            $start = CarbonImmutable::parse($data['starts_at']);
            $end = CarbonImmutable::parse($data['ends_at']);

            if (! $end->gt($start)) {
                throw ValidationException::withMessages([
                    'ends_at' => ['Blackout end time must be after start time.'],
                ]);
            }

            $this->assertBlackoutDoesNotOverlap($facility, $data);

            return $facility->facilityBlackouts()->create([
                ...$data,
                'created_by' => $actor->getKey(),
            ]);
        });
    }

    private function assertScheduleDoesNotOverlap(BuildingFacility $facility, array $data, ?int $ignoreId = null): void
    {
        $start = $this->minutes($data['start_time']);
        $end = $this->minutes($data['end_time']);

        $overlap = $facility->facilitySchedules()
            ->where('day_of_week', $data['day_of_week'])
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->contains(fn (FacilitySchedule $schedule): bool =>
                $this->minutes($schedule->start_time) < $end
                && $this->minutes($schedule->end_time) > $start
            );

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_time' => ['The facility schedule overlaps an existing schedule for this day.'],
            ]);
        }
    }

    private function assertTimeSlotInsideSchedule(FacilitySchedule $schedule, array $data): void
    {
        $start = $this->minutes($data['start_time']);
        $end = $this->minutes($data['end_time']);

        if ($start < $this->minutes($schedule->start_time) || $end > $this->minutes($schedule->end_time)) {
            throw ValidationException::withMessages([
                'start_time' => ['The time slot must be fully inside its facility schedule.'],
            ]);
        }
    }

    private function assertTimeSlotDoesNotOverlap(FacilitySchedule $schedule, array $data, ?int $ignoreId = null): void
    {
        $start = $this->minutes($data['start_time']);
        $end = $this->minutes($data['end_time']);

        $overlap = $schedule->facilityTimeSlots()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->contains(fn (FacilityTimeSlot $slot): bool =>
                $this->minutes($slot->start_time) < $end
                && $this->minutes($slot->end_time) > $start
            );

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_time' => ['The facility time slot overlaps an existing time slot.'],
            ]);
        }
    }

    private function assertBlackoutDoesNotOverlap(BuildingFacility $facility, array $data): void
    {
        $start = CarbonImmutable::parse($data['starts_at']);
        $end = CarbonImmutable::parse($data['ends_at']);

        $overlap = $facility->facilityBlackouts()
            ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $start->format('Y-m-d H:i:s'))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_at' => ['The blackout period overlaps an existing blackout.'],
            ]);
        }
    }

    private function assertTimeOrder(string $start, string $end, string $field): void
    {
        if ($this->minutes($end) <= $this->minutes($start)) {
            throw ValidationException::withMessages([
                $field => ['End time must be after start time.'],
            ]);
        }
    }

    private function minutes(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));
        return ($parts[0] * 60) + ($parts[1] ?? 0);
    }
}
