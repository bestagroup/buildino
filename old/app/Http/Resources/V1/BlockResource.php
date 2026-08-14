<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'title' => $this->title,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,

            'floors_count' => $this->whenCounted('floors'),

            'building' => $this->whenLoaded(
                'building',
                fn (): array => [
                    'id' => $this->building->id,
                    'complex_id' => $this->building->complex_id,
                    'code' => $this->building->code,
                    'title' => $this->building->title,
                ]
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
