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
            'start_charge_period_id'=>$this->start_charge_period_id,
            'period_installment_count'=>$this->period_installment_count,
            'period_allocations'=>$this->whenLoaded('periodAllocations', fn () => $this->periodAllocations->map(fn ($row) => [
                'period_id'=>$row->charge_period_id, 'title'=>$row->chargePeriod?->title, 'sequence'=>$row->sequence, 'amount'=>$row->amount, 'issued_invoice_id'=>$row->issued_invoice_id,
            ])->values()),
            'charge_period_id'=>$this->charge_period_id,'invoice_number'=>$this->invoice_number,
            'payer_responsibility'=>is_object($this->payer_responsibility)
                ? $this->payer_responsibility->value
                : $this->payer_responsibility,
            'payer_user_id'=>$this->payer_user_id,
            'payer'=>$this->whenLoaded('payer',fn()=> $this->payer ? [
                'id'=>$this->payer->id,
                'name'=>trim("{$this->payer->first_name} {$this->payer->last_name}"),
            ] : null),
            'issue_date'=>$this->issue_date?->toDateString(),'due_date'=>$this->due_date?->toDateString(),
            'period_start'=>$this->period_start?->toDateString(),'period_end'=>$this->period_end?->toDateString(),
            'subtotal'=>(int)$this->subtotal,'discount_amount'=>(int)$this->discount_amount,
            'penalty_amount'=>(int)$this->penalty_amount,
            'credit_applied_amount'=>(int)$this->credit_applied_amount,
            'credit_remaining_amount'=>(int)$this->credit_remaining_amount,
            'adjustment_amount'=>(int)$this->adjustment_amount,
            'adjustment_reason'=>$this->adjustment_reason,
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
