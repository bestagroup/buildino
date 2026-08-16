<?php

namespace App\Http\Requests;

use App\Enums\FinancialOperationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuildingIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => [
                'sometimes',
                'integer',
                Rule::in([
                    (int) $this->route('income')?->building_id,
                ]),
            ],
            'fund_id' => 'sometimes|nullable|integer|exists:funds,id',
            'financial_category_id' => 'sometimes|nullable|integer|exists:financial_categories,id',
            'title' => 'sometimes|string|max:255',
            'amount' => 'sometimes|integer|min:1',
            'income_date' => 'sometimes|date',
            'reference_number' => 'sometimes|nullable|string|max:100',
            'status' => ['sometimes', Rule::enum(FinancialOperationStatus::class)],
            'description' => 'sometimes|nullable|string',
        ];
    }
}
