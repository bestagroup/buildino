<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletAccountingPostingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,

            'wallet_transfer_id' =>
                $this->wallet_transfer_id,

            'building_id' => $this->building_id,

            'financial_transaction_id' =>
                $this->financial_transaction_id,

            'status' => is_object($this->status)
                ? $this->status->value
                : $this->status,

            'mapping_key' => $this->mapping_key,
            'reason' => $this->reason,

            'mapping_snapshot' =>
                $this->mapping_snapshot,

            'attempts' => (int) $this->attempts,

            'last_error' => $this->last_error,

            'posted_at' =>
                $this->posted_at?->toISOString(),

            'financial_transaction' =>
                $this->whenLoaded(
                    'financialTransaction',
                    fn () => $this
                        ->financialTransaction
                        ? [
                            'id' =>
                                $this
                                    ->financialTransaction
                                    ->id,
                            'uuid' =>
                                $this
                                    ->financialTransaction
                                    ->uuid,
                            'transaction_type' =>
                                is_object(
                                    $this
                                        ->financialTransaction
                                        ->transaction_type
                                )
                                    ? $this
                                        ->financialTransaction
                                        ->transaction_type
                                        ->value
                                    : $this
                                        ->financialTransaction
                                        ->transaction_type,
                            'occurred_at' =>
                                $this
                                    ->financialTransaction
                                    ->occurred_at
                                    ?->toISOString(),
                        ]
                        : null
                ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
