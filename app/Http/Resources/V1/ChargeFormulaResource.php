<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargeFormulaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'building_id'=>$this->building_id,
            'title'=>$this->title,
            'calculation_type'=>is_object($this->calculation_type)
                ? $this->calculation_type->value
                : $this->calculation_type,
            'configuration'=>$this->configuration,
            'is_active'=>(bool)$this->is_active,
            'items'=>$this->whenLoaded(
                'chargeItems',
                fn()=>$this->chargeItems->map(fn($item)=>[
                    'id'=>$item->id,
                    'financial_category_id'=>$item->financial_category_id,
                    'title'=>$item->title,
                    'base_amount'=>(int)$item->base_amount,
                    'configuration'=>$item->configuration,
                ])->values()
            ),
            'created_at'=>$this->created_at?->toISOString(),
            'updated_at'=>$this->updated_at?->toISOString(),
        ];
    }
}
