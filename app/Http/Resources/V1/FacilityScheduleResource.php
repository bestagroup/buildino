<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_facility_id' => $this->building_facility_id,
            'day_of_week' => (int) $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'is_active' => (bool) $this->is_active,
            'time_slots' => FacilityTimeSlotResource::collection(
                $this->whenLoaded('facilityTimeSlots')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
