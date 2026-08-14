<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderBankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'bank_name' => $this->bank_name,
            'account_holder_name' =>
                $this->account_holder_name,
            'iban' => $this->iban,
            'account_number' => $this->account_number,
            'card_number' => $this->card_number,
            'is_default' => (bool) $this->is_default,
            'is_verified' => (bool) $this->is_verified,
            'is_active' => (bool) $this->is_active,
            'verified_by' => $this->verified_by,
            'verified_at' =>
                $this->verified_at?->toISOString(),
            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
