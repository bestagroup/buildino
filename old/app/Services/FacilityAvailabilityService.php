<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\BuildingFacility;
use Carbon\CarbonImmutable;

final class FacilityAvailabilityService
{
    public function forDate(BuildingFacility $facility, string $date): array
    {
        $facility->loadMissing([
            'building',
            'facilitySchedules.facilityTimeSlots',
            'facilityBlackouts',
        ]);

        $timezone = $facility->building?->timezone ?: config('app.timezone');
        $day = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $dayEnd = $day->endOfDay();

        $schedules = $facility->facilitySchedules
            ->where('is_active', true)
            ->where('day_of_week', $day->dayOfWeek)
            ->values();

        $blackouts = $facility->facilityBlackouts
            ->filter(fn ($blackout): bool =>
                $blackout->starts_at->lt($dayEnd)
                && $blackout->ends_at->gt($day)
            )
            ->values();

        $reservations = $facility->facilityReservations()
            ->whereDate('reservation_date', $day->toDateString())
            ->whereIn('status', $this->blockingStatuses())
            ->get();

        $slots = [];

        foreach ($schedules as $schedule) {
            foreach ($schedule->facilityTimeSlots->where('is_active', true) as $slot) {
                $startAt = $this->dateTime($day, $slot->start_time);
                $endAt = $this->dateTime($day, $slot->end_time);

                $blocked = $blackouts->contains(fn ($blackout): bool =>
                    $blackout->starts_at->lt($endAt)
                    && $blackout->ends_at->gt($startAt)
                );

                $reservedCount = $reservations->filter(fn ($reservation): bool =>
                    $this->minutes($reservation->start_time) < $this->minutes($slot->end_time)
                    && $this->minutes($reservation->end_time) > $this->minutes($slot->start_time)
                )->count();

                $capacity = max(1, (int) ($slot->capacity ?? 1));

                $slots[] = [
                    'id' => $slot->id,
                    'schedule_id' => $schedule->id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'capacity' => $capacity,
                    'reserved_count' => $reservedCount,
                    'remaining_capacity' => max(0, $capacity - $reservedCount),
                    'price' => (int) $slot->price,
                    'is_blackout' => $blocked,
                    'is_available' => ! $blocked && $startAt->isFuture() && $reservedCount < $capacity,
                ];
            }
        }

        return [
            'facility_id' => $facility->id,
            'date' => $day->toDateString(),
            'day_of_week' => $day->dayOfWeek,
            'facility_is_active' => (bool) $facility->is_active,
            'schedules' => $schedules->map(fn ($schedule): array => [
                'id' => $schedule->id,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
            ])->values()->all(),
            'slots' => $slots,
            'blackouts' => $blackouts->map(fn ($blackout): array => [
                'id' => $blackout->id,
                'starts_at' => $blackout->starts_at?->toISOString(),
                'ends_at' => $blackout->ends_at?->toISOString(),
                'reason' => $blackout->reason,
            ])->values()->all(),
        ];
    }

    private function blockingStatuses(): array
    {
        return [
            ReservationStatus::Pending->value,
            ReservationStatus::PaymentPending->value,
            ReservationStatus::Approved->value,
            ReservationStatus::Confirmed->value,
        ];
    }

    private function dateTime(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute, $second] = array_pad(array_map('intval', explode(':', $time)), 3, 0);
        return $day->setTime($hour, $minute, $second);
    }

    private function minutes(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));
        return ($parts[0] * 60) + ($parts[1] ?? 0);
    }
}
