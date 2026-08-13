<?php

namespace App\Http\Resources\V1;

use App\Enums\FacilityType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingFacilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'title' => $this->title,
            'code' => $this->code,
            'description' => $this->description,
            'image' => $this->image,

            'type' => $this->type instanceof FacilityType
                ? $this->type->value
                : $this->type,

            'capacity' => $this->capacity,
            'default_price' => (int) $this->default_price,
            'requires_payment' => (bool) $this->requires_payment,
            'requires_approval' => (bool) $this->requires_approval,
            'is_active' => (bool) $this->is_active,

            'building' => $this->whenLoaded(
                'building',
                fn (): array => [
                    'id' => $this->building->id,
                    'complex_id' => $this->building->complex_id,
                    'code' => $this->building->code,
                    'title' => $this->building->title,
                ]
            ),

            'schedules' => FacilityScheduleResource::collection(
                $this->whenLoaded('facilitySchedules')
            ),

            'reservation_rule' => $this->when(
                $this->relationLoaded('facilityReservationRules'),
                fn () => $this->facilityReservationRules->isNotEmpty()
                    ? (new FacilityReservationRuleResource(
                        $this->facilityReservationRules->first()
                    ))->resolve($request)
                    : null
            ),

            'blackouts' => FacilityBlackoutResource::collection(
                $this->whenLoaded('facilityBlackouts')
            ),

            'reservations_count' => $this->whenCounted(
                'facilityReservations'
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
