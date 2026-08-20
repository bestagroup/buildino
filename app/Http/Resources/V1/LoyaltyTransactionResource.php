<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type instanceof \BackedEnum
                ? $this->type->value
                : $this->type,
            'points' => (int) $this->points,
            'balance_after' => (int) $this->balance_after,
            'remaining_points' => $this->remaining_points === null
                ? null
                : (int) $this->remaining_points,
            'description' => $this->description,
            'expires_at' => $this->expires_at?->toISOString(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
