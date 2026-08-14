<?php
namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingBillPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,'uuid'=>$this->uuid,'building_id'=>$this->building_id,
            'wallet_id'=>$this->wallet_id,
            'bill_type'=>is_object($this->bill_type)?$this->bill_type->value:$this->bill_type,
            'bill_identifier'=>$this->bill_identifier,
            'payment_identifier'=>$this->payment_identifier,
            'amount'=>(int)$this->amount,
            'status'=>is_object($this->status)?$this->status->value:$this->status,
            'provider'=>$this->provider,
            'provider_reference'=>$this->provider_reference,
            'failure_reason'=>$this->failure_reason,
            'completed_at'=>$this->completed_at?->toISOString(),
            'failed_at'=>$this->failed_at?->toISOString(),
        ];
    }
}
