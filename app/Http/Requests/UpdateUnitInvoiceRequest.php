<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'sometimes|integer|exists:buildings,id',
            'unit_id' => 'sometimes|integer|exists:units,id',
            'charge_period_id' => 'sometimes|nullable|integer|exists:charge_periods,id',
            'invoice_number' => ['sometimes', 'string', 'max:100', Rule::unique('unit_invoices', 'invoice_number')->ignore($this->route('unit_invoice')?->id ?? $this->route('unit_invoice'))],
            'issue_date' => 'sometimes|date',
            'due_date' => 'sometimes|date|after_or_equal:issue_date',
            'subtotal' => 'sometimes|integer|min:0',
            'discount_amount' => 'sometimes|integer|min:0',
            'penalty_amount' => 'sometimes|integer|min:0',
            'total_amount' => 'sometimes|integer|min:0',
            'paid_amount' => 'sometimes|integer|min:0',
            'outstanding_amount' => 'sometimes|integer|min:0',
            'status' => ['sometimes', 'sometimes', Rule::enum(InvoiceStatus::class)],
            'description' => 'sometimes|nullable|string',
        ];
    }
}
