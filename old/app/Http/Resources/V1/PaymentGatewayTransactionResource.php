<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentGatewayTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $response = is_array(
            $this->response_payload
        )
            ? $this->response_payload
            : [];

        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,

            'gateway' => $this->gateway,

            'authority' => $this->authority,

            'gateway_transaction_id' =>
                $this->gateway_transaction_id,

            'tracking_code' =>
                $this->tracking_code,

            'reference_number' =>
                $this->reference_number,

            'redirect_url' =>
                $response['redirect_url']
                ?? null,

            'requested_at' =>
                $this->requested_at?->toISOString(),

            'verified_at' =>
                $this->verified_at?->toISOString(),

            'failed_at' =>
                $this->failed_at?->toISOString(),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
