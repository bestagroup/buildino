<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderPayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'provider_user_id' =>
                $this->provider_user_id,
            'wallet_id' => $this->wallet_id,
            'provider_bank_account_id' =>
                $this->provider_bank_account_id,
            'amount' => (int) $this->amount,
            'fee_amount' => (int) $this->fee_amount,
            'net_amount' => (int) $this->net_amount,
            'status' => is_object($this->status)
                ? $this->status->value
                : $this->status,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'paid_by' => $this->paid_by,
            'wallet_transfer_id' =>
                $this->wallet_transfer_id,
            'bank_reference' =>
                $this->bank_reference,
            'rejection_reason' =>
                $this->rejection_reason,
            'approved_at' =>
                $this->approved_at?->toISOString(),
            'paid_at' =>
                $this->paid_at?->toISOString(),
            'rejected_at' =>
                $this->rejected_at?->toISOString(),
            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
