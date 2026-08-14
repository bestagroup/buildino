<?php

namespace App\Services;

use App\Enums\ReservationApprovalType;
use App\Enums\ReservationStatus;
use App\Events\FacilityReservationApproved;
use App\Events\FacilityReservationCreated;
use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\FacilityReservationRule;
use App\Models\FacilitySchedule;
use App\Models\FacilityTimeSlot;
use App\Models\ReservationCancellation;
use App\Enums\RefundStatus;
use App\Services\Facility\FacilityWalletPaymentService;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FacilityReservationService
{
    public function __construct(
        private readonly FacilityWalletPaymentService $walletPayments
    ) {
    }

    public function create(array $data): FacilityReservation
    {
        return DB::transaction(function () use ($data): FacilityReservation {
            $facility = BuildingFacility::query()
                ->with([
                    'building',
                    'facilityReservationRules',
                    'facilitySchedules.facilityTimeSlots',
                    'facilityBlackouts',
                ])
                ->findOrFail($data['building_facility_id']);

            if (! $facility->is_active) {
                throw ValidationException::withMessages([
                    'building_facility_id' => ['The selected facility is inactive.'],
                ]);
            }

            $unit = Unit::query()
                ->with('floor.block.building')
                ->findOrFail($data['unit_id']);

            $this->assertSameBuilding($facility, $unit);

            $timezone = $facility->building?->timezone ?: config('app.timezone');
            $date = CarbonImmutable::parse($data['reservation_date'], $timezone)->startOfDay();

            $slot = $this->resolveTimeSlot(
                $facility,
                isset($data['facility_time_slot_id']) ? (int) $data['facility_time_slot_id'] : null,
                $date
            );

            if ($slot) {
                $data['start_time'] = $slot->start_time;
                $data['end_time'] = $slot->end_time;
            }

            if (blank($data['start_time'] ?? null) || blank($data['end_time'] ?? null)) {
                throw ValidationException::withMessages([
                    'start_time' => ['Start and end time are required when no time slot is selected.'],
                ]);
            }

            $startAt = $this->dateTime($date, $data['start_time']);
            $endAt = $this->dateTime($date, $data['end_time']);

            if (! $endAt->gt($startAt)) {
                throw ValidationException::withMessages([
                    'end_time' => ['Reservation end time must be after start time.'],
                ]);
            }

            /** @var FacilityReservationRule|null $rule */
            $rule = $facility->facilityReservationRules->first();

            $this->assertAdvanceWindow($startAt, $rule, $timezone);
            $this->assertDuration($startAt, $endAt, $rule);
            $this->assertInsideSchedule($facility, $date, $startAt, $endAt);
            $this->assertNotBlackout($facility, $startAt, $endAt);
            $this->assertReservationLimits($facility, $unit, $date, $rule);
            $this->assertCapacityAndConflict(
                $facility,
                $slot,
                $date,
                $data['start_time'],
                $data['end_time']
            );

            $price = $slot ? (int) $slot->price : (int) $facility->default_price;
            $discount = 0;
            $finalAmount = max(0, $price - $discount);

            /*
             * Reservation approval lifecycle and payment lifecycle are
             * intentionally independent concerns.
             *
             * Backward-compatible rule:
             * - When the facility does NOT require payment, preserve the
             *   original reservation lifecycle exactly:
             *     manual approval => pending
             *     auto confirm     => approved
             *
             * Wallet rule:
             * - Only facilities explicitly configured with
             *   requires_payment=true and a positive final amount enter
             *   payment_pending.
             */
            $requiresPayment = $facility->requires_payment === true
                && $finalAmount > 0;

            $autoConfirm = (bool) ($rule?->auto_confirm ?? false)
                && ! $facility->requires_approval;

            if ($requiresPayment) {
                $reservationStatus = ReservationStatus::PaymentPending;
                $approvedAt = null;
            } else {
                $reservationStatus = $autoConfirm
                    ? ReservationStatus::Approved
                    : ReservationStatus::Pending;

                $approvedAt = $autoConfirm
                    ? now()
                    : null;
            }

            $reservationData = [
                'uuid' => $data['uuid'] ?? (string) str()->uuid(),
                'building_facility_id' => $facility->getKey(),
                'facility_time_slot_id' => $slot?->getKey(),
                'unit_id' => $unit->getKey(),
                'user_id' => $data['user_id'],
                'reservation_date' => $date->toDateString(),
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'price' => $price,
                'discount_amount' => $discount,
                'final_amount' => $finalAmount,
                'rule_snapshot' => $this->ruleSnapshot($rule),
                'status' => $reservationStatus,
                'approval_type' => $autoConfirm
                    ? ReservationApprovalType::Automatic
                    : ReservationApprovalType::Manual,
                'description' => $data['description'] ?? null,
                'approved_at' => $approvedAt,
            ];

            $reservation = FacilityReservation::query()->create(
                $reservationData
            );

            DB::afterCommit(function () use (
                $reservation,
                $autoConfirm,
                $requiresPayment
            ): void {
                FacilityReservationCreated::dispatch(
                    $reservation->fresh()
                );

                /*
                 * Preserve the original auto-confirm event semantics for
                 * non-payment facilities, regardless of their display price.
                 */
                if ($autoConfirm && ! $requiresPayment) {
                    FacilityReservationApproved::dispatch(
                        $reservation->fresh()
                    );
                }
            });

            return $reservation;
        }, 3);
    }

    public function approve(FacilityReservation $reservation, User $user): FacilityReservation
    {
        return DB::transaction(function () use ($reservation, $user): FacilityReservation {
            $reservation = FacilityReservation::query()
                ->lockForUpdate()
                ->findOrFail($reservation->getKey());

            if ($reservation->status !== ReservationStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending reservations can be approved.'],
                ]);
            }

            $reservation->update([
                'status' => ReservationStatus::Approved,
                'approved_by' => $user->getKey(),
                'approved_at' => now(),
            ]);

            $approved = $reservation->refresh();

            DB::afterCommit(fn () => FacilityReservationApproved::dispatch($approved));

            return $approved;
        }, 3);
    }

    public function reject(FacilityReservation $reservation, User $user): FacilityReservation
    {
        return DB::transaction(function () use ($reservation, $user): FacilityReservation {
            $reservation = FacilityReservation::query()
                ->lockForUpdate()
                ->findOrFail($reservation->getKey());

            if ($reservation->status !== ReservationStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending reservations can be rejected.'],
                ]);
            }

            $reservation->update([
                'status' => ReservationStatus::Rejected,
                'approved_by' => $user->getKey(),
                'approved_at' => now(),
            ]);

            return $reservation->refresh();
        }, 3);
    }

    public function cancel(
        FacilityReservation $reservation,
        User $user,
        ?string $reason = null,
        bool $force = false
    ): FacilityReservation {
        $result = DB::transaction(function () use (
            $reservation,
            $user,
            $reason,
            $force
        ): array {
            $reservation = FacilityReservation::query()
                ->with([
                    'buildingFacility.building',
                    'buildingFacility.facilityReservationRules',
                    'walletPayment',
                ])
                ->lockForUpdate()
                ->findOrFail($reservation->getKey());

            if ($reservation->status === ReservationStatus::Cancelled) {
                return [
                    'reservation' => $reservation,
                    'cancellation' => $reservation
                        ->reservationCancellations()
                        ->latest('id')
                        ->first(),
                ];
            }

            if (! in_array($reservation->status, [
                ReservationStatus::Pending,
                ReservationStatus::PaymentPending,
                ReservationStatus::Approved,
                ReservationStatus::Confirmed,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => ['This reservation can no longer be cancelled.'],
                ]);
            }

            $currentRule = $reservation
                ->buildingFacility
                ?->facilityReservationRules
                ?->first();

            $ruleData = $reservation->rule_snapshot
                ?: $this->ruleSnapshot($currentRule)
                ?: [];

            $cancelBeforeMinutes = (int) (
                $ruleData['cancel_before_minutes'] ?? 0
            );

            $timezone = $reservation
                ->buildingFacility
                ?->building
                ?->timezone
                ?: config('app.timezone');

            $startAt = CarbonImmutable::parse(
                $reservation->reservation_date->toDateString()
                    .' '
                    .$reservation->start_time,
                $timezone
            );

            if (
                ! $force
                && $cancelBeforeMinutes > 0
                && CarbonImmutable::now($timezone)
                    ->addMinutes($cancelBeforeMinutes)
                    ->gt($startAt)
            ) {
                throw ValidationException::withMessages([
                    'status' => ['The cancellation deadline for this reservation has passed.'],
                ]);
            }

            $cancellationFee = min(
                (int) $reservation->final_amount,
                (int) ($ruleData['cancellation_fee'] ?? 0)
            );

            $refundPercentage = min(
                100,
                max(
                    0,
                    (int) ($ruleData['refund_percentage'] ?? 100)
                )
            );

            $refundableBase = max(
                0,
                (int) $reservation->final_amount - $cancellationFee
            );

            $refundAmount = $reservation->walletPayment
                ? (int) floor(
                    $refundableBase * ($refundPercentage / 100)
                )
                : 0;

            $cancellation = ReservationCancellation::query()->create([
                'facility_reservation_id' => $reservation->getKey(),
                'cancelled_by' => $user->getKey(),
                'reason' => $reason,
                'cancellation_fee' => $cancellationFee,
                'refund_amount' => $refundAmount,
                'refund_status' => $refundAmount > 0
                    ? RefundStatus::Pending
                    : null,
                'refund_payment_id' => null,
                'refund_wallet_transfer_id' => null,
                'cancelled_at' => now(),
            ]);

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
            ]);

            return [
                'reservation' => $reservation->refresh(),
                'cancellation' => $cancellation,
            ];
        }, 3);

        /*
         * Cancellation itself must remain valid even if the building
         * wallet temporarily cannot fund the refund. In that case the
         * refund stays pending and can be retried by support.
         */
        if (
            $result['cancellation']
            && (int) $result['cancellation']->refund_amount > 0
        ) {
            try {
                $this->walletPayments->refund(
                    $result['cancellation'],
                    $user
                );
            } catch (ValidationException) {
                // Keep refund_status=pending for later retry.
            }
        }

        return $result['reservation']->refresh();
    }

    private function resolveTimeSlot(
        BuildingFacility $facility,
        ?int $timeSlotId,
        CarbonImmutable $date
    ): ?FacilityTimeSlot {
        if ($timeSlotId === null) {
            return null;
        }

        $slot = FacilityTimeSlot::query()
            ->with('facilitySchedule')
            ->findOrFail($timeSlotId);

        $schedule = $slot->facilitySchedule;

        if (! $schedule || (int) $schedule->building_facility_id !== (int) $facility->getKey()) {
            throw ValidationException::withMessages([
                'facility_time_slot_id' => ['The selected time slot does not belong to this facility.'],
            ]);
        }

        if (! $slot->is_active || ! $schedule->is_active) {
            throw ValidationException::withMessages([
                'facility_time_slot_id' => ['The selected time slot is inactive.'],
            ]);
        }

        if ((int) $schedule->day_of_week !== (int) $date->dayOfWeek) {
            throw ValidationException::withMessages([
                'reservation_date' => ['The selected time slot is not available on this date.'],
            ]);
        }

        return $slot;
    }

    private function assertSameBuilding(BuildingFacility $facility, Unit $unit): void
    {
        $unitBuildingId = $unit->floor?->block?->building?->getKey();

        if ($unitBuildingId === null || (int) $unitBuildingId !== (int) $facility->building_id) {
            throw ValidationException::withMessages([
                'unit_id' => ['The selected unit does not belong to the facility building.'],
            ]);
        }
    }

    private function assertAdvanceWindow(
        CarbonImmutable $startAt,
        ?FacilityReservationRule $rule,
        string $timezone
    ): void {
        $now = CarbonImmutable::now($timezone);

        if (! $startAt->isFuture()) {
            throw ValidationException::withMessages([
                'reservation_date' => ['Facility reservations must be in the future.'],
            ]);
        }

        $minAdvance = (int) ($rule?->min_advance_minutes ?? 0);
        if ($minAdvance > 0 && $now->addMinutes($minAdvance)->gt($startAt)) {
            throw ValidationException::withMessages([
                'reservation_date' => ['The reservation does not satisfy the minimum advance time.'],
            ]);
        }

        if ($rule?->max_advance_days !== null
            && $startAt->gt($now->addDays((int) $rule->max_advance_days))) {
            throw ValidationException::withMessages([
                'reservation_date' => ['The reservation is beyond the maximum advance booking window.'],
            ]);
        }
    }

    private function assertDuration(
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        ?FacilityReservationRule $rule
    ): void {
        $duration = $startAt->diffInMinutes($endAt);

        if ($rule?->min_duration_minutes !== null
            && $duration < (int) $rule->min_duration_minutes) {
            throw ValidationException::withMessages([
                'end_time' => ['The reservation duration is shorter than the facility minimum.'],
            ]);
        }

        if ($rule?->max_duration_minutes !== null
            && $duration > (int) $rule->max_duration_minutes) {
            throw ValidationException::withMessages([
                'end_time' => ['The reservation duration exceeds the facility maximum.'],
            ]);
        }
    }

    private function assertInsideSchedule(
        BuildingFacility $facility,
        CarbonImmutable $date,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt
    ): void {
        $allActiveSchedules = $facility->facilitySchedules->where('is_active', true);

        if ($allActiveSchedules->isEmpty()) {
            return;
        }

        $daySchedules = $allActiveSchedules->where('day_of_week', $date->dayOfWeek);
        $startMinutes = ($startAt->hour * 60) + $startAt->minute;
        $endMinutes = ($endAt->hour * 60) + $endAt->minute;

        $inside = $daySchedules->contains(fn (FacilitySchedule $schedule): bool =>
            $this->minutes($schedule->start_time) <= $startMinutes
            && $this->minutes($schedule->end_time) >= $endMinutes
        );

        if (! $inside) {
            throw ValidationException::withMessages([
                'start_time' => ['The requested time is outside the active facility schedule.'],
            ]);
        }
    }

    private function assertNotBlackout(
        BuildingFacility $facility,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt
    ): void {
        $blocked = $facility->facilityBlackouts()
            ->where('starts_at', '<', $endAt->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $startAt->format('Y-m-d H:i:s'))
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages([
                'reservation_date' => ['The facility is unavailable during the selected blackout period.'],
            ]);
        }
    }

    private function assertReservationLimits(
        BuildingFacility $facility,
        Unit $unit,
        CarbonImmutable $date,
        ?FacilityReservationRule $rule
    ): void {
        if (! $rule) {
            return;
        }

        $base = FacilityReservation::query()
            ->where('building_facility_id', $facility->getKey())
            ->where('unit_id', $unit->getKey())
            ->whereIn('status', $this->blockingStatuses());

        if ($rule->max_reservation_per_unit !== null
            && (clone $base)->whereDate('reservation_date', '>=', now()->toDateString())->count()
                >= (int) $rule->max_reservation_per_unit) {
            throw ValidationException::withMessages([
                'unit_id' => ['This unit has reached the maximum number of active facility reservations.'],
            ]);
        }

        if ($rule->max_reservations_per_day !== null
            && (clone $base)->whereDate('reservation_date', $date->toDateString())->count()
                >= (int) $rule->max_reservations_per_day) {
            throw ValidationException::withMessages([
                'reservation_date' => ['This unit has reached the daily reservation limit.'],
            ]);
        }

        if ($rule->max_reservations_per_week !== null
            && (clone $base)->whereBetween('reservation_date', [
                $date->startOfWeek()->toDateString(),
                $date->endOfWeek()->toDateString(),
            ])->count() >= (int) $rule->max_reservations_per_week) {
            throw ValidationException::withMessages([
                'reservation_date' => ['This unit has reached the weekly reservation limit.'],
            ]);
        }

        if ($rule->max_reservations_per_month !== null
            && (clone $base)->whereBetween('reservation_date', [
                $date->startOfMonth()->toDateString(),
                $date->endOfMonth()->toDateString(),
            ])->count() >= (int) $rule->max_reservations_per_month) {
            throw ValidationException::withMessages([
                'reservation_date' => ['This unit has reached the monthly reservation limit.'],
            ]);
        }
    }

    private function assertCapacityAndConflict(
        BuildingFacility $facility,
        ?FacilityTimeSlot $slot,
        CarbonImmutable $date,
        string $startTime,
        string $endTime
    ): void {
        $query = FacilityReservation::query()
            ->where('building_facility_id', $facility->getKey())
            ->whereDate('reservation_date', $date->toDateString())
            ->whereIn('status', $this->blockingStatuses())
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->lockForUpdate();

        if ($slot !== null) {
            $capacity = max(1, (int) ($slot->capacity ?? 1));

            if ((clone $query)->count() >= $capacity) {
                throw ValidationException::withMessages([
                    'reservation_date' => ['The selected facility time slot has reached capacity.'],
                ]);
            }

            return;
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'reservation_date' => ['The selected facility time overlaps an existing reservation.'],
            ]);
        }
    }

    private function ruleSnapshot(?FacilityReservationRule $rule): ?array
    {
        return $rule?->only([
            'min_duration_minutes',
            'max_duration_minutes',
            'min_advance_minutes',
            'max_advance_days',
            'max_reservations_per_day',
            'max_reservations_per_week',
            'max_reservations_per_month',
            'max_reservation_per_unit',
            'cancel_before_minutes',
            'cancellation_fee',
            'refund_percentage',
            'allow_guest',
            'auto_confirm',
        ]);
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

    private function dateTime(CarbonImmutable $date, string $time): CarbonImmutable
    {
        [$hour, $minute, $second] = array_pad(array_map('intval', explode(':', $time)), 3, 0);
        return $date->setTime($hour, $minute, $second);
    }

    private function minutes(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));
        return ($parts[0] * 60) + ($parts[1] ?? 0);
    }
}
