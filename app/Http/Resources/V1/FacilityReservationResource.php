<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'building_facility_id' => $this->building_facility_id,
            'facility_time_slot_id' => $this->facility_time_slot_id,
            'unit_id' => $this->unit_id,
            'user_id' => $this->user_id,

            'reservation_date' => $this->reservation_date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,

            'price' => (int) $this->price,
            'discount_amount' => (int) $this->discount_amount,
            'final_amount' => (int) $this->final_amount,

            'status' => is_object($this->status)
                ? $this->status->value
                : $this->status,

            'approval_type' => is_object($this->approval_type)
                ? $this->approval_type->value
                : $this->approval_type,

            'description' => $this->description,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),

            'facility' => $this->whenLoaded(
                'buildingFacility',
                fn (): array => [
                    'id' => $this->buildingFacility->id,
                    'building_id' => $this->buildingFacility->building_id,
                    'title' => $this->buildingFacility->title,
                    'code' => $this->buildingFacility->code,
                    'type' => is_object($this->buildingFacility->type)
                        ? $this->buildingFacility->type->value
                        : $this->buildingFacility->type,
                ]
            ),

            'time_slot' => $this->whenLoaded(
                'facilityTimeSlot',
                fn (): ?array => $this->facilityTimeSlot
                    ? [
                        'id' => $this->facilityTimeSlot->id,
                        'start_time' => $this->facilityTimeSlot->start_time,
                        'end_time' => $this->facilityTimeSlot->end_time,
                        'capacity' => $this->facilityTimeSlot->capacity,
                        'price' => (int) $this->facilityTimeSlot->price,
                    ]
                    : null
            ),

            'unit' => $this->whenLoaded(
                'unit',
                fn (): array => [
                    'id' => $this->unit->id,
                    'floor_id' => $this->unit->floor_id,
                    'unit_number' => $this->unit->unit_number,
                    'title' => $this->unit->title,
                ]
            ),

            'user' => $this->whenLoaded(
                'user',
                fn (): array => [
                    'id' => $this->user->id,
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                ]
            ),

            'approver' => $this->whenLoaded(
                'approvedBy',
                fn (): ?array => $this->approvedBy
                    ? [
                        'id' => $this->approvedBy->id,
                        'first_name' => $this->approvedBy->first_name,
                        'last_name' => $this->approvedBy->last_name,
                    ]
                    : null
            ),

            'rule_snapshot' => $this->rule_snapshot,

            'cancellations' => $this->whenLoaded(
                'reservationCancellations',
                fn () => $this->reservationCancellations
                    ->map(fn ($cancellation): array => [
                        'id' => $cancellation->id,
                        'cancelled_by' => $cancellation->cancelled_by,
                        'reason' => $cancellation->reason,
                        'cancellation_fee' => (int) $cancellation->cancellation_fee,
                        'refund_amount' => (int) $cancellation->refund_amount,
                        'refund_status' => is_object($cancellation->refund_status)
                            ? $cancellation->refund_status->value
                            : $cancellation->refund_status,
                        'cancelled_at' => $cancellation->cancelled_at?->toISOString(),
                    ])
                    ->values()
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
