<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'mobile' => $this->mobile,
            'national_code' => $this->national_code,
            'vehicle_plate' => $this->vehicle_plate,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
