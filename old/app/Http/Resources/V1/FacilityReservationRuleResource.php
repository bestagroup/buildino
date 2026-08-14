<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityReservationRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_facility_id' => $this->building_facility_id,
            'min_duration_minutes' => $this->min_duration_minutes,
            'max_duration_minutes' => $this->max_duration_minutes,
            'min_advance_minutes' => $this->min_advance_minutes,
            'max_advance_days' => $this->max_advance_days,
            'max_reservations_per_day' => $this->max_reservations_per_day,
            'max_reservations_per_week' => $this->max_reservations_per_week,
            'max_reservations_per_month' => $this->max_reservations_per_month,
            'max_reservation_per_unit' => $this->max_reservation_per_unit,
            'cancel_before_minutes' => $this->cancel_before_minutes,
            'cancellation_fee' => (int) $this->cancellation_fee,
            'refund_percentage' => (int) $this->refund_percentage,
            'allow_guest' => (bool) $this->allow_guest,
            'auto_confirm' => (bool) $this->auto_confirm,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
