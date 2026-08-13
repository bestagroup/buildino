<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitOwnershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'user_id' => $this->user_id,

            'ownership_percentage' => $this->ownership_percentage !== null
                ? (float) $this->ownership_percentage
                : null,

            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),

            'is_primary' => (bool) $this->is_primary,
            'is_active' => (bool) $this->is_active,

            'created_by' => $this->created_by,
            'ended_by' => $this->ended_by,
            'notes' => $this->notes,

            'user' => $this->whenLoaded(
                'user',
                fn (): array => [
                    'id' => $this->user->id,
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                    'mobile' => $this->user->mobile,
                    'email' => $this->user->email,
                ]
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

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
