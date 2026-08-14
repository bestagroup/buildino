<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestWalletPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,

            'service_request_id' =>
                $this->service_request_id,

            'service_request_quote_id' =>
                $this->service_request_quote_id,

            'payer_source' =>
                is_object($this->payer_source)
                    ? $this->payer_source->value
                    : $this->payer_source,

            'source_wallet_id' =>
                $this->source_wallet_id,

            'provider_wallet_id' =>
                $this->provider_wallet_id,

            'platform_wallet_id' =>
                $this->platform_wallet_id,

            'amount' => (int) $this->amount,

            'provider_amount' =>
                (int) $this->provider_amount,

            'commission_amount' =>
                (int) $this->commission_amount,

            'status' => is_object($this->status)
                ? $this->status->value
                : $this->status,

            'provider_transfer_id' =>
                $this->provider_transfer_id,

            'commission_transfer_id' =>
                $this->commission_transfer_id,

            'locked_at' =>
                $this->locked_at?->toISOString(),

            'settled_at' =>
                $this->settled_at?->toISOString(),

            'released_at' =>
                $this->released_at?->toISOString(),

            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
