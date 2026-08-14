<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'complex_id' => $this->complex_id,

            'complex' => $this->whenLoaded('complex', function (): array {
                return [
                    'id' => $this->complex->id,
                    'code' => $this->complex->code,
                    'title' => $this->complex->title,
                ];
            }),

            'code' => $this->code,
            'title' => $this->title,
            'building_number' => $this->building_number,

            'location' => [
                'address' => $this->address,
                'postal_code' => $this->postal_code,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],

            'timezone' => $this->timezone,
            'currency' => $this->currency,

            'statistics' => [
                'floors_count' => $this->floors_count,
                'units_count' => $this->units_count,
                'parking_count' => $this->parking_count,
                'storage_count' => $this->storage_count,
            ],

            'construction_year' => $this->construction_year,
            'is_active' => $this->is_active,

            'blocks_count' => $this->whenCounted('blocks'),
            'facilities_count' => $this->whenCounted('buildingFacilities'),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
