<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'building_id' => $this->building_id,
            'plan' => $this->whenLoaded('plan', fn (): array => [
                'id' => $this->plan->id,
                'code' => $this->plan->code,
                'title' => $this->plan->title,
                'duration_days' => $this->plan->duration_days,
            ]),
            'status' => $this->status?->value ?? $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'grace_ends_at' => $this->grace_ends_at?->toIso8601String(),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'limits' => $this->limits,
            'renewed_from_id' => $this->renewed_from_id,
        ];
    }
}
