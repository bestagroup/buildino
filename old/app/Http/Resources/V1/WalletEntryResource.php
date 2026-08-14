<?php

namespace App\Http\Resources\V1;

use App\Enums\WalletEntryType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $transfer = $this->whenLoaded('transfer');

        $counterpartyWalletId = null;

        if ($this->relationLoaded('transfer') && $this->transfer) {
            $entryType = is_object($this->entry_type)
                ? $this->entry_type
                : WalletEntryType::from($this->entry_type);

            $counterpartyWalletId =
                $entryType === WalletEntryType::Debit
                    ? $this->transfer->destination_wallet_id
                    : $this->transfer->source_wallet_id;
        }

        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,

            'entry_type' => is_object($this->entry_type)
                ? $this->entry_type->value
                : $this->entry_type,

            'amount' => (int) $this->amount,
            'balance_after' =>
                (int) $this->balance_after,

            'counterparty_wallet_id' =>
                $counterpartyWalletId,

            'transfer' => $this->when(
                $this->relationLoaded('transfer')
                    && $this->transfer,
                fn (): array => [
                    'id' => $this->transfer->id,
                    'uuid' => $this->transfer->uuid,

                    'type' => is_object($this->transfer->type)
                        ? $this->transfer->type->value
                        : $this->transfer->type,

                    'status' => is_object($this->transfer->status)
                        ? $this->transfer->status->value
                        : $this->transfer->status,

                    'source_wallet_id' =>
                        $this->transfer->source_wallet_id,

                    'destination_wallet_id' =>
                        $this->transfer->destination_wallet_id,

                    'reference' => [
                        'type' =>
                            $this->transfer->reference_type,
                        'id' =>
                            $this->transfer->reference_id,
                    ],

                    'description' =>
                        $this->transfer->description,

                    'completed_at' =>
                        $this->transfer
                            ->completed_at
                            ?->toISOString(),
                ]
            ),

            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
