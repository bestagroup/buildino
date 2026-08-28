<?php

namespace App\Services\Charge;

use App\Enums\ExpenseAllocationMethod;
use App\Enums\ExpensePayerResponsibility;
use App\Enums\FinancialCategoryType;
use App\Models\BuildingExpense;
use App\Models\BuildingExpenseAllocationRule;
use App\Models\FinancialCategory;

final class BuildingExpenseRuleService
{
    public function synchronize(BuildingExpense $expense): BuildingExpenseAllocationRule
    {
        if (! $expense->financial_category_id) {
            $category = FinancialCategory::query()->firstOrCreate(
                [
                    'building_id' => $expense->building_id,
                    'title' => 'هزینه‌های عمومی',
                    'type' => FinancialCategoryType::Expense->value,
                ],
                [
                    'is_active' => true,
                ]
            );

            $expense->update([
                'financial_category_id' => $category->getKey(),
            ]);
        }

        return BuildingExpenseAllocationRule::query()->updateOrCreate(
            [
                'building_id' => $expense->building_id,
                'financial_category_id' => $expense->financial_category_id,
            ],
            [
                'allocation_method' => $expense->allocation_method
                    ?? ExpenseAllocationMethod::Equal,
                'payer_responsibility' => $expense->payer_responsibility
                    ?? ExpensePayerResponsibility::Unit,
                'configuration' => $expense->allocation_configuration,
                'is_active' => true,
            ]
        );
    }
}
