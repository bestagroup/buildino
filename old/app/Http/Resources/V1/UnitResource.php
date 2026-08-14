<?php

namespace App\Http\Resources\V1;

use App\Enums\UnitUsageType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'floor_id' => $this->floor_id,
            'unit_number' => $this->unit_number,
            'title' => $this->title,

            'area' => $this->area !== null
                ? (float) $this->area
                : null,

            'bedrooms' => $this->bedrooms !== null
                ? (int) $this->bedrooms
                : null,

            'usage_type' => $this->usage_type instanceof UnitUsageType
                ? $this->usage_type->value
                : $this->usage_type,

            'is_active' => (bool) $this->is_active,

            'floor' => $this->whenLoaded(
                'floor',
                fn (): array => [
                    'id' => $this->floor->id,
                    'block_id' => $this->floor->block_id,
                    'floor_number' => (int) $this->floor->floor_number,
                    'title' => $this->floor->title,

                    'block' => $this->floor->relationLoaded('block')
                        ? [
                            'id' => $this->floor->block->id,
                            'building_id' => $this->floor->block->building_id,
                            'title' => $this->floor->block->title,

                            'building' => $this->floor->block->relationLoaded('building')
                                ? [
                                    'id' => $this->floor->block->building->id,
                                    'complex_id' => $this->floor->block->building->complex_id,
                                    'code' => $this->floor->block->building->code,
                                    'title' => $this->floor->block->building->title,
                                ]
                                : null,
                        ]
                        : null,
                ]
            ),

            'ownerships' => $this->whenLoaded(
                'unitOwnerships',
                fn () => $this->unitOwnerships
                    ->map(
                        fn ($ownership): array => [
                            'id' => $ownership->id,
                            'user_id' => $ownership->user_id,
                            'ownership_percentage' => $ownership->ownership_percentage,
                            'is_primary' => (bool) $ownership->is_primary,
                            'is_active' => (bool) $ownership->is_active,
                            'starts_at' => $ownership->starts_at?->toDateString(),
                            'ends_at' => $ownership->ends_at?->toDateString(),
                        ]
                    )
                    ->values()
            ),

            'occupancies' => $this->whenLoaded(
                'unitOccupancies',
                fn () => $this->unitOccupancies
                    ->map(
                        fn ($occupancy): array => [
                            'id' => $occupancy->id,
                            'user_id' => $occupancy->user_id,
                            'occupancy_type' => is_object($occupancy->occupancy_type)
                                ? $occupancy->occupancy_type->value
                                : $occupancy->occupancy_type,
                            'is_primary' => (bool) $occupancy->is_primary,
                            'is_active' => (bool) $occupancy->is_active,
                            'starts_at' => $occupancy->starts_at?->toDateString(),
                            'ends_at' => $occupancy->ends_at?->toDateString(),
                        ]
                    )
                    ->values()
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
