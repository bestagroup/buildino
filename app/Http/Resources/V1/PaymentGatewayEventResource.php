<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentGatewayEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'gateway' => $this->gateway,
            'event_key' => $this->event_key,
            'payment_transaction_id' =>
                $this->payment_transaction_id,
            'event_type' =>
                is_object($this->event_type)
                    ? $this->event_type->value
                    : $this->event_type,
            'authority' => $this->authority,
            'payload_hash' =>
                $this->payload_hash,
            'signature_valid' =>
                $this->signature_valid,
            'source_ip' => $this->source_ip,
            'status' =>
                is_object($this->status)
                    ? $this->status->value
                    : $this->status,
            'attempts' =>
                (int) $this->attempts,
            'error_message' =>
                $this->error_message,
            'received_at' =>
                $this->received_at?->toISOString(),
            'processed_at' =>
                $this->processed_at?->toISOString(),
            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
