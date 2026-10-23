<?php

namespace App\Services\Charge;

use App\Enums\ChargeCalculationType;
use App\Enums\ExpenseAllocationMethod;
use App\Enums\ExpensePayerResponsibility;
use App\Enums\FinancialCategoryType;
use App\Models\BuildingExpense;
use App\Models\BuildingExpenseAllocationRule;
use App\Models\ChargeFormula;
use App\Models\FinancialCategory;

final class BuildingExpenseRuleService
{
    public function synchronizeFromFormula(
        BuildingExpense $expense,
        ChargeFormula $formula
    ): BuildingExpenseAllocationRule {
        if (! $expense->financial_category_id) {
            return $this->synchronize($expense);
        }

        $type = $formula->calculation_type
            instanceof ChargeCalculationType
                ? $formula->calculation_type
                : ChargeCalculationType::from(
                    (string) $formula->calculation_type
                );

        $method = match ($type) {
            ChargeCalculationType::Equal =>
                ExpenseAllocationMethod::Equal,
            ChargeCalculationType::Area =>
                ExpenseAllocationMethod::Area,
            ChargeCalculationType::Persons =>
                ExpenseAllocationMethod::Persons,
            ChargeCalculationType::FloorCoefficient =>
                ExpenseAllocationMethod::Custom,
            ChargeCalculationType::Fixed =>
                ExpenseAllocationMethod::Equal,
        };

        return BuildingExpenseAllocationRule::query()
            ->updateOrCreate(
                [
                    'building_id' =>
                        $expense->building_id,
                    'financial_category_id' =>
                        $expense->financial_category_id,
                ],
                [
                    'allocation_method' => $method,
                    'payer_responsibility' =>
                        $expense->payer_responsibility
                        ?? ExpensePayerResponsibility::Unit,
                    'configuration' => [
                        'source' => 'charge_formula',
                        'charge_formula_id' =>
                            $formula->getKey(),
                        'floor_coefficients' =>
                            $formula->configuration[
                                'floor_coefficients'
                            ] ?? null,
                    ],
                    'is_active' => true,
                ]
            );
    }

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
