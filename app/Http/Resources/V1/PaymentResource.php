<?php
namespace App\Http\Resources\V1;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,'uuid'=>$this->uuid,'building_id'=>$this->building_id,
            'payer_user_id'=>$this->payer_user_id,'payment_number'=>$this->payment_number,
            'amount'=>(int)$this->amount,'currency'=>$this->currency,
            'method'=>is_object($this->method)?$this->method->value:$this->method,
            'status'=>is_object($this->status)?$this->status->value:$this->status,
            'paid_at'=>$this->paid_at?->toISOString(),'verified_at'=>$this->verified_at?->toISOString(),
            'verified_by'=>$this->verified_by,'description'=>$this->description,
            'allocations'=>$this->whenLoaded('paymentAllocations',fn()=>$this->paymentAllocations->map(fn($a)=>[
                'id'=>$a->id,'payable_type'=>$a->payable_type,'payable_id'=>$a->payable_id,'amount'=>(int)$a->amount,
            ])->values()),
            'created_at'=>$this->created_at?->toISOString(),
            'updated_at'=>$this->updated_at?->toISOString(),
        ];
    }
}
