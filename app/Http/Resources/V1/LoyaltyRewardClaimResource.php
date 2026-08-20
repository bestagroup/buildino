<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyRewardClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loyalty_reward_id' => $this->loyalty_reward_id,
            'user_id' => $this->user_id,
            'loyalty_transaction_id' => $this->loyalty_transaction_id,
            'status' => $this->status instanceof \BackedEnum
                ? $this->status->value
                : $this->status,
            'claimed_at' => $this->claimed_at?->toISOString(),
            'processed_by' => $this->processed_by,
            'processed_at' => $this->processed_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'user_name' => $this->whenLoaded(
                'user',
                fn (): string => trim(
                    ($this->user?->first_name ?? '')
                    .' '
                    .($this->user?->last_name ?? '')
                ) ?: (string) ($this->user?->mobile ?? '')
            ),
            'processed_by_name' => $this->whenLoaded(
                'processedBy',
                fn (): string => trim(
                    ($this->processedBy?->first_name ?? '')
                    .' '
                    .($this->processedBy?->last_name ?? '')
                ) ?: (string) ($this->processedBy?->mobile ?? '')
            ),
            'reward' => new LoyaltyRewardResource(
                $this->whenLoaded('loyaltyReward')
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
