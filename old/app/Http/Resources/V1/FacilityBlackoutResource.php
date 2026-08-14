<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityBlackoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_facility_id' => $this->building_facility_id,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'reason' => $this->reason,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded(
                'createdBy',
                fn (): ?array => $this->createdBy
                    ? [
                        'id' => $this->createdBy->id,
                        'first_name' => $this->createdBy->first_name,
                        'last_name' => $this->createdBy->last_name,
                    ]
                    : null
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
