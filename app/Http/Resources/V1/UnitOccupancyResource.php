<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitOccupancyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'user_id' => $this->user_id,

            'occupancy_type' => is_object($this->occupancy_type)
                ? $this->occupancy_type->value
                : $this->occupancy_type,

            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),

            'is_primary' => (bool) $this->is_primary,
            'is_active' => (bool) $this->is_active,
            'is_current_owner' => $this->relationLoaded('unit')
                && $this->unit?->relationLoaded('unitOwnerships')
                && $this->unit->unitOwnerships->contains(fn ($ownership): bool =>
                    (int) $ownership->user_id === (int) $this->user_id
                    && $ownership->is_active
                    && (! $ownership->starts_at || $ownership->starts_at->toDateString() <= now()->toDateString())
                    && (! $ownership->ends_at || $ownership->ends_at->toDateString() >= now()->toDateString())
                ),
            'resident_count' =>
                $this->relationLoaded('unit')
                    ? (int) $this->unit->resident_count
                    : 0,

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
                    'account_active' => (bool) $this->user->is_active && ! (bool) $this->user->is_blocked,
                    'mobile_verified' => $this->user->mobile_verified_at !== null,
                    'email_verified' => $this->user->email_verified_at !== null,
                ]
            ),

            'unit' => $this->whenLoaded(
                'unit',
                fn (): array => [
                    'id' => $this->unit->id,
                    'floor_id' => $this->unit->floor_id,
                    'unit_number' => $this->unit->unit_number,
                    'title' => $this->unit->title,
                    'resident_count' =>
                        (int) $this->unit->resident_count,
                ]
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
