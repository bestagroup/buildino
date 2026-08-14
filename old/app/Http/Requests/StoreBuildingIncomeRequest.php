<?php

namespace App\Http\Requests;

use App\Enums\FinancialOperationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuildingIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'required|integer|exists:buildings,id',
            'fund_id' => 'nullable|integer|exists:funds,id',
            'financial_category_id' => 'nullable|integer|exists:financial_categories,id',
            'title' => 'required|string|max:255',
            'amount' => 'required|integer|min:1',
            'income_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'status' => ['sometimes', Rule::enum(FinancialOperationStatus::class)],
            'description' => 'nullable|string',
        ];
    }
}
