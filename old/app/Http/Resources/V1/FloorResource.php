<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FloorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'block_id' => $this->block_id,
            'floor_number' => (int) $this->floor_number,
            'title' => $this->title,
            'sort_order' => (int) $this->sort_order,

            'units_count' => $this->whenCounted('units'),

            'block' => $this->whenLoaded(
                'block',
                fn (): array => [
                    'id' => $this->block->id,
                    'building_id' => $this->block->building_id,
                    'title' => $this->block->title,

                    'building' => $this->block->relationLoaded('building')
                        ? [
                            'id' => $this->block->building->id,
                            'complex_id' => $this->block->building->complex_id,
                            'code' => $this->block->building->code,
                            'title' => $this->block->building->title,
                        ]
                        : null,
                ]
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
