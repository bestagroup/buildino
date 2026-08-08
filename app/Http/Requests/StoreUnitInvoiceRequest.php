<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'required|integer|exists:buildings,id',
            'unit_id' => 'required|integer|exists:units,id',
            'charge_period_id' => 'nullable|integer|exists:charge_periods,id',
            'invoice_number' => 'required|string|max:100|unique:unit_invoices,invoice_number',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'subtotal' => 'sometimes|integer|min:0',
            'discount_amount' => 'sometimes|integer|min:0',
            'penalty_amount' => 'sometimes|integer|min:0',
            'total_amount' => 'sometimes|integer|min:0',
            'paid_amount' => 'sometimes|integer|min:0',
            'outstanding_amount' => 'sometimes|integer|min:0',
            'status' => ['sometimes', Rule::enum(InvoiceStatus::class)],
            'description' => 'nullable|string',
        ];
    }
}
