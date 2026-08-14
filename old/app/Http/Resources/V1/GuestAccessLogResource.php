<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestAccessLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guest_visit_id' => $this->guest_visit_id,

            'action' => is_object($this->action)
                ? $this->action->value
                : $this->action,

            'occurred_at' => $this->occurred_at?->toISOString(),

            'gate' => $this->gate,
            'entry_method' => $this->entry_method,
            'verified_by' => $this->verified_by,
            'vehicle_plate' => $this->vehicle_plate,
            'notes' => $this->notes,

            'verifier' => $this->whenLoaded(
                'verifiedBy',
                fn (): ?array => $this->verifiedBy
                    ? [
                        'id' => $this->verifiedBy->id,
                        'first_name' => $this->verifiedBy->first_name,
                        'last_name' => $this->verifiedBy->last_name,
                    ]
                    : null
            ),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
