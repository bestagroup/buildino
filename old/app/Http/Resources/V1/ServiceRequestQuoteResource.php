<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,

            'service_request_id' =>
                $this->service_request_id,

            'provider_user_id' =>
                $this->provider_user_id,

            'amount' => (int) $this->amount,

            'commission_rate_bps' =>
                (int) $this->commission_rate_bps,

            'commission_amount' =>
                (int) $this->commission_amount,

            'provider_amount' =>
                (int) $this->provider_amount,

            'status' => is_object($this->status)
                ? $this->status->value
                : $this->status,

            'notes' => $this->notes,

            'valid_until' =>
                $this->valid_until?->toISOString(),

            'accepted_by' => $this->accepted_by,

            'accepted_at' =>
                $this->accepted_at?->toISOString(),

            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
