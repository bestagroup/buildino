<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletReconciliationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'wallet_id' => $this->wallet_id,
            'reconciled_at' =>
                $this->reconciled_at?->toISOString(),
            'entry_balance' =>
                (int) $this->entry_balance,
            'stored_balance' =>
                (int) $this->stored_balance,
            'expected_locked_balance' =>
                (int) $this->expected_locked_balance,
            'stored_locked_balance' =>
                (int) $this->stored_locked_balance,
            'balance_difference' =>
                (int) $this->balance_difference,
            'lock_difference' =>
                (int) $this->lock_difference,
            'status' => is_object($this->status)
                ? $this->status->value
                : $this->status,
            'details' => $this->details,
            'created_by' => $this->created_by,
            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
