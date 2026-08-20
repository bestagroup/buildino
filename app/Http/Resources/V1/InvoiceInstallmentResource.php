<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceInstallmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_invoice_id' => $this->unit_invoice_id,
            'installment_number' => (int) $this->installment_number,
            'due_date' => $this->due_date?->toDateString(),
            'amount' => (int) $this->amount,
            'paid_amount' => (int) $this->paid_amount,
            'outstanding_amount' => max(
                0,
                (int) $this->amount
                + (int) $this->penalty_amount
                - (int) $this->waived_amount
                - (int) $this->paid_amount
            ),
            'penalty_amount' => (int) $this->penalty_amount,
            'waived_amount' => (int) $this->waived_amount,
            'status' => $this->status instanceof \BackedEnum
                ? $this->status->value
                : $this->status,
            'paid_at' => $this->paid_at?->toISOString(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
