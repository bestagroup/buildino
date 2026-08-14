<?php
namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletPayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,'uuid'=>$this->uuid,'building_id'=>$this->building_id,
            'wallet_id'=>$this->wallet_id,
            'building_bank_account_id'=>$this->building_bank_account_id,
            'amount'=>(int)$this->amount,'fee_amount'=>(int)$this->fee_amount,
            'net_amount'=>(int)$this->net_amount,
            'status'=>is_object($this->status)?$this->status->value:$this->status,
            'requested_by'=>$this->requested_by,'approved_by'=>$this->approved_by,
            'paid_by'=>$this->paid_by,'bank_reference'=>$this->bank_reference,
            'rejection_reason'=>$this->rejection_reason,
            'approved_at'=>$this->approved_at?->toISOString(),
            'paid_at'=>$this->paid_at?->toISOString(),
            'rejected_at'=>$this->rejected_at?->toISOString(),
        ];
    }
}
