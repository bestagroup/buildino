<?php
namespace App\Http\Resources\V1;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class UnitInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,'building_id'=>$this->building_id,'unit_id'=>$this->unit_id,
            'charge_period_id'=>$this->charge_period_id,'invoice_number'=>$this->invoice_number,
            'issue_date'=>$this->issue_date?->toDateString(),'due_date'=>$this->due_date?->toDateString(),
            'period_start'=>$this->period_start?->toDateString(),'period_end'=>$this->period_end?->toDateString(),
            'subtotal'=>(int)$this->subtotal,'discount_amount'=>(int)$this->discount_amount,
            'penalty_amount'=>(int)$this->penalty_amount,
            'waived_penalty_amount'=>(int)$this->waived_penalty_amount,
            'total_amount'=>(int)$this->total_amount,
            'paid_amount'=>(int)$this->paid_amount,'outstanding_amount'=>(int)$this->outstanding_amount,
            'status'=>is_object($this->status)?$this->status->value:$this->status,
            'description'=>$this->description,
            'unit'=>$this->whenLoaded('unit',fn()=>[
                'id'=>$this->unit->id,'unit_number'=>$this->unit->unit_number,'title'=>$this->unit->title,
            ]),
            'items'=>$this->whenLoaded('invoiceItems',fn()=>$this->invoiceItems->map(fn($item)=>[
                'id'=>$item->id,'charge_item_id'=>$item->charge_item_id,'title'=>$item->title,
                'description'=>$item->description,'quantity'=>(int)$item->quantity,
                'unit_amount'=>(int)$item->unit_amount,'total_amount'=>(int)$item->total_amount,
                'metadata'=>$item->metadata,
            ])->values()),
            'installments' => InvoiceInstallmentResource::collection(
                $this->whenLoaded('invoiceInstallments')
            ),
            'created_at'=>$this->created_at?->toISOString(),
            'updated_at'=>$this->updated_at?->toISOString(),
        ];
    }
}
