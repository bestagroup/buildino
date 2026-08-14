<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,

            'owner' => [
                'type' => $this->ownerKind(),
                'id' => $this->owner_id,
            ],

            'currency' => $this->currency,

            'balance' => (int) $this->balance,
            'locked_balance' =>
                (int) $this->locked_balance,

            'available_balance' =>
                $this->availableBalance(),

            'is_active' => (bool) $this->is_active,

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
