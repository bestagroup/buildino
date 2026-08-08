<?php

namespace App\Services;

use App\Enums\ReservationApprovalType;
use App\Enums\ReservationStatus;
use App\Events\FacilityReservationApproved;
use App\Events\FacilityReservationCreated;
use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FacilityReservationService
{
    public function create(array $data): FacilityReservation
    {
        return DB::transaction(function () use ($data): FacilityReservation {
            $facility = BuildingFacility::query()
                ->with('facilityReservationRules')
                ->findOrFail($data['building_facility_id']);

            $conflict = FacilityReservation::query()
                ->where('building_facility_id', $facility->getKey())
                ->whereDate('reservation_date', $data['reservation_date'])
                ->whereIn('status', [
                    ReservationStatus::Pending->value,
                    ReservationStatus::Approved->value,
                    ReservationStatus::Confirmed->value,
                ])
                ->where('start_time', '<', $data['end_time'])
                ->where('end_time', '>', $data['start_time'])
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'reservation_date' => 'The selected facility time overlaps an existing reservation.',
                ]);
            }

            $rule = $facility->facilityReservationRules->first();
            $autoConfirm = (bool) ($rule?->auto_confirm ?? false);

            $data['uuid'] ??= (string) str()->uuid();
            $data['rule_snapshot'] = $rule?->toArray();
            $data['approval_type'] = $autoConfirm
                ? ReservationApprovalType::Automatic
                : ReservationApprovalType::Manual;
            $data['status'] = $autoConfirm
                ? ReservationStatus::Approved
                : ReservationStatus::Pending;
            $data['approved_at'] = $autoConfirm ? now() : null;

            $reservation = FacilityReservation::query()->create($data);

            DB::afterCommit(function () use ($reservation, $autoConfirm): void {
                FacilityReservationCreated::dispatch($reservation->fresh());

                if ($autoConfirm) {
                    FacilityReservationApproved::dispatch($reservation->fresh());
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
                    'status' => 'Only pending reservations can be approved.',
                ]);
            }

            $reservation->update([
                'status' => ReservationStatus::Approved,
                'approved_by' => $user->getKey(),
                'approved_at' => now(),
            ]);

            $approved = $reservation->refresh();

            DB::afterCommit(
                fn () => FacilityReservationApproved::dispatch($approved)
            );

            return $approved;
        }, 3);
    }
}
