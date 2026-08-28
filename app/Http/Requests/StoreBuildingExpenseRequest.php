<?php

namespace App\Http\Requests;

use App\Enums\ExpenseAllocationMethod;
use App\Enums\ExpensePayerResponsibility;
use App\Enums\FinancialOperationStatus;
use App\Models\Block;
use App\Models\FinancialCategory;
use App\Models\Fund;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuildingExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => 'required|integer|exists:buildings,id',
            'block_id' => 'nullable|integer|exists:blocks,id',
            'fund_id' => 'nullable|integer|exists:funds,id',
            'financial_category_id' => 'nullable|integer|exists:financial_categories,id',
            'allocation_method' => ['sometimes', Rule::enum(ExpenseAllocationMethod::class)],
            'allocation_configuration' => 'nullable|array',
            'payer_responsibility' => ['sometimes', Rule::enum(ExpensePayerResponsibility::class)],
            'title' => 'required|string|max:255',
            'amount' => 'required|integer|min:1',
            'expense_date' => 'required|date',
            'invoice_number' => 'nullable|string|max:100',
            'status' => ['sometimes', Rule::enum(FinancialOperationStatus::class)],
            'description' => 'nullable|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $buildingId = $this->integer('building_id');

            if (
                $this->filled('block_id')
                && ! Block::query()
                    ->whereKey($this->integer('block_id'))
                    ->where('building_id', $buildingId)
                    ->exists()
            ) {
                $validator->errors()->add(
                    'block_id',
                    'بلوک انتخاب‌شده متعلق به ساختمان هزینه نیست.'
                );
            }

            if (
                $this->filled('fund_id')
                && ! Fund::query()
                    ->whereKey($this->integer('fund_id'))
                    ->where('building_id', $buildingId)
                    ->exists()
            ) {
                $validator->errors()->add(
                    'fund_id',
                    'صندوق انتخاب‌شده متعلق به ساختمان هزینه نیست.'
                );
            }

            if ($this->filled('financial_category_id')) {
                $category = FinancialCategory::query()->find(
                    $this->integer('financial_category_id')
                );

                if (
                    $category?->building_id !== null
                    && (int) $category->building_id !== $buildingId
                ) {
                    $validator->errors()->add(
                        'financial_category_id',
                        'دسته مالی انتخاب‌شده متعلق به ساختمان هزینه نیست.'
                    );
                }
            }
        });
    }
}
