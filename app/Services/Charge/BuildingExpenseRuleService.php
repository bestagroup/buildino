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
use Illuminate\Validation\ValidationException;

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

    public function synchronize(BuildingExpense $expense, bool $requireFormula = false): BuildingExpenseAllocationRule
    {
        if ($requireFormula || $expense->payment_status !== null) {
            if (! $expense->financial_category_id) {
                throw ValidationException::withMessages([
                    'financial_category_id' => 'انتخاب فرمول شارژ الزامی است.',
                ]);
            }

            $formulas = ChargeFormula::query()
                ->where('building_id', $expense->building_id)
                ->where('is_active', true)
                ->whereHas('chargeItems', fn ($query) => $query
                    ->where('financial_category_id', $expense->financial_category_id))
                ->with('chargeItems')
                ->get();

            if ($formulas->isNotEmpty()) {
                $formula = $formulas->first();
                if ($formulas->count() !== 1 || $formula->chargeItems->count() !== 1
                    || $formula->calculation_type === ChargeCalculationType::Fixed) {
                    throw ValidationException::withMessages([
                        'financial_category_id' => 'برای این هزینه باید دقیقاً یک فرمول فعال غیرثابت تعریف شده باشد.',
                    ]);
                }

                $rule = $this->synchronizeFromFormula($expense, $formula);
                $expense->update([
                    'allocation_method' => $rule->allocation_method,
                    'allocation_configuration' => $rule->configuration,
                ]);

                return $rule;
            }

            throw ValidationException::withMessages([
                'financial_category_id' => 'فرمول شارژ فعال و متعلق به همین ساختمان را انتخاب کنید.',
            ]);
        }

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
