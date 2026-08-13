<?php
namespace App\Http\Resources\V1;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class FinancialTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,'uuid'=>$this->uuid,'building_id'=>$this->building_id,
            'transaction_type'=>is_object($this->transaction_type)?$this->transaction_type->value:$this->transaction_type,
            'occurred_at'=>$this->occurred_at?->toISOString(),'description'=>$this->description,
            'created_by'=>$this->created_by,
            'entries'=>$this->whenLoaded('financialLedgerEntries',fn()=>$this->financialLedgerEntries->map(fn($e)=>[
                'id'=>$e->id,'financial_account_id'=>$e->financial_account_id,
                'entry_type'=>is_object($e->entry_type)?$e->entry_type->value:$e->entry_type,
                'amount'=>(int)$e->amount,'currency'=>$e->currency,'metadata'=>$e->metadata,
            ])->values()),
            'created_at'=>$this->created_at?->toISOString(),
        ];
    }
}
