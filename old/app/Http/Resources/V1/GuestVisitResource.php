<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guest_id' => $this->guest_id,
            'unit_id' => $this->unit_id,
            'registered_by' => $this->registered_by,

            'expected_entry_at' => $this->expected_entry_at?->toISOString(),
            'expected_exit_at' => $this->expected_exit_at?->toISOString(),

            'status' => is_object($this->status)
                ? $this->status->value
                : $this->status,

            'description' => $this->description,

            'guest' => $this->whenLoaded(
                'guest',
                fn () => (
                    new GuestResource(
                        $this->guest
                    )
                )->resolve($request)
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

            'registrar' => $this->whenLoaded(
                'registeredBy',
                fn (): array => [
                    'id' => $this->registeredBy->id,
                    'first_name' => $this->registeredBy->first_name,
                    'last_name' => $this->registeredBy->last_name,
                ]
            ),

            'access_logs_count' => $this->whenCounted(
                'guestAccessLogs'
            ),

            'access_logs' => GuestAccessLogResource::collection(
                $this->whenLoaded(
                    'guestAccessLogs'
                )
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
