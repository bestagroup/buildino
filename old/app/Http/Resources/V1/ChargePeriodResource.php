<?php
namespace App\Http\Resources\V1;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ChargePeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,'building_id'=>$this->building_id,'title'=>$this->title,
            'period_start'=>$this->period_start?->toDateString(),
            'period_end'=>$this->period_end?->toDateString(),
            'due_date'=>$this->due_date?->toDateString(),
            'status'=>is_object($this->status)?$this->status->value:$this->status,
            'created_by'=>$this->created_by,
            'calculations_count'=>$this->whenCounted('chargeCalculations'),
            'invoices_count'=>$this->whenCounted('unitInvoices'),
            'created_at'=>$this->created_at?->toISOString(),
            'updated_at'=>$this->updated_at?->toISOString(),
        ];
    }
}
