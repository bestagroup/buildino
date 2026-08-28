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

class UpdateBuildingExpenseRequest extends FormRequest
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
                    (int) $this->route('expense')?->building_id,
                ]),
            ],
            'block_id' => 'sometimes|nullable|integer|exists:blocks,id',
            'fund_id' => 'sometimes|nullable|integer|exists:funds,id',
            'financial_category_id' => 'sometimes|nullable|integer|exists:financial_categories,id',
            'allocation_method' => ['sometimes', Rule::enum(ExpenseAllocationMethod::class)],
            'allocation_configuration' => 'sometimes|nullable|array',
            'payer_responsibility' => ['sometimes', Rule::enum(ExpensePayerResponsibility::class)],
            'title' => 'sometimes|string|max:255',
            'amount' => 'sometimes|integer|min:1',
            'expense_date' => 'sometimes|date',
            'invoice_number' => 'sometimes|nullable|string|max:100',
            'status' => ['sometimes', Rule::enum(FinancialOperationStatus::class)],
            'description' => 'sometimes|nullable|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $expense = $this->route('expense');
            $buildingId = (int) $expense?->building_id;
            $blockId = $this->exists('block_id')
                ? $this->input('block_id')
                : $expense?->block_id;
            $fundId = $this->exists('fund_id')
                ? $this->input('fund_id')
                : $expense?->fund_id;
            $categoryId = $this->exists('financial_category_id')
                ? $this->input('financial_category_id')
                : $expense?->financial_category_id;

            if (
                $blockId !== null
                && ! Block::query()
                    ->whereKey((int) $blockId)
                    ->where('building_id', $buildingId)
                    ->exists()
            ) {
                $validator->errors()->add(
                    'block_id',
                    'بلوک انتخاب‌شده متعلق به ساختمان هزینه نیست.'
                );
            }

            if (
                $fundId !== null
                && ! Fund::query()
                    ->whereKey((int) $fundId)
                    ->where('building_id', $buildingId)
                    ->exists()
            ) {
                $validator->errors()->add(
                    'fund_id',
                    'صندوق انتخاب‌شده متعلق به ساختمان هزینه نیست.'
                );
            }

            if ($categoryId !== null) {
                $category = FinancialCategory::query()->find((int) $categoryId);

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
