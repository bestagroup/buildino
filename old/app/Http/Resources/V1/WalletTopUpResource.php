<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTopUpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,

            'payment_id' => $this->payment_id,
            'wallet_id' => $this->wallet_id,

            'target_type' => $this->targetKind(),
            'target_id' => $this->target_id,

            'amount' => (int) $this->amount,
            'currency' => $this->currency,

            'status' => is_object($this->status)
                ? $this->status->value
                : $this->status,

            'wallet_transfer_id' =>
                $this->wallet_transfer_id,

            'credited_at' =>
                $this->credited_at?->toISOString(),

            'retry_attempted_at' =>
                $this->retry_attempted_at?->toISOString(),

            'retry_summary' => $this->retry_summary,

            'wallet' => $this->whenLoaded(
                'wallet',
                fn (): array => [
                    'id' => $this->wallet->id,
                    'balance' => (int) $this->wallet->balance,
                    'locked_balance' =>
                        (int) $this->wallet->locked_balance,
                    'available_balance' =>
                        $this->wallet->availableBalance(),
                    'currency' => $this->wallet->currency,
                ]
            ),

            'payment' => $this->whenLoaded(
                'payment',
                fn (): array => [
                    'id' => $this->payment->id,
                    'payment_number' =>
                        $this->payment->payment_number,
                    'status' => is_object(
                        $this->payment->status
                    )
                        ? $this->payment->status->value
                        : $this->payment->status,
                    'method' => is_object(
                        $this->payment->method
                    )
                        ? $this->payment->method->value
                        : $this->payment->method,
                    'amount' =>
                        (int) $this->payment->amount,
                    'verified_at' =>
                        $this->payment
                            ->verified_at
                            ?->toISOString(),
                ]
            ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
