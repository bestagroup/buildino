<?php

namespace Tests\Feature\Financial;

use App\Actions\BuildingExpense\CreateBuildingExpense;
use App\Actions\BuildingExpense\UpdateBuildingExpense;
use App\Enums\ExpenseAllocationMethod;
use App\Models\ChargeFormula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class ExpenseFormPersistenceTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_expense_without_payment_status_is_posted_and_uses_formula_and_preserves_hidden_data_on_edit(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();
        $category = $graph['building']->financialCategories()->where('default_key', 'energy.3')->firstOrFail();
        $formula = ChargeFormula::create(['building_id' => $graph['building']->id, 'title' => 'آب', 'calculation_type' => 'persons', 'is_active' => true]);
        $formula->chargeItems()->create(['financial_category_id' => $category->id, 'title' => 'آب', 'base_amount' => 0]);
        $expense = app(CreateBuildingExpense::class)->execute([
            'building_id' => $graph['building']->id, 'block_id' => $graph['block']->id,
            'financial_category_id' => $category->id, 'title' => 'قبض آب', 'amount' => 1000,
            'expense_date' => today(), 'payer_responsibility' => 'resident',
        ], $actor);
        $this->assertSame('posted', $expense->status);
        $this->assertNotNull($expense->posted_at);
        $this->assertSame(ExpenseAllocationMethod::Persons, $expense->allocation_method);
        $this->assertSame($formula->id, $expense->allocation_configuration['charge_formula_id']);
        $expense->update(['payment_status' => 'paid']);
        $edited = app(UpdateBuildingExpense::class)->execute($expense, ['title' => 'قبض آب آبان'], $actor);
        $this->assertSame('paid', $edited->payment_status);
        $this->assertSame($graph['block']->id, $edited->block_id);
        $this->assertSame('posted', $edited->status);
        $this->assertSame(ExpenseAllocationMethod::Persons, $edited->allocation_method);

        $formula->update(['is_active' => false]);
        try {
            app(UpdateBuildingExpense::class)->execute($edited, ['amount' => 2000], $actor);
            $this->fail('Inactive formula accepted');
        } catch (ValidationException) {
            $this->assertSame(1000, $edited->fresh()->amount);
        }
    }

    public function test_missing_formula_rolls_back_new_expense_without_payment_status(): void
    {
        $graph = $this->createBuildingGraph();
        $category = $graph['building']->financialCategories()->where('default_key', 'energy.3')->firstOrFail();
        try {
            app(CreateBuildingExpense::class)->execute([
                'building_id' => $graph['building']->id, 'financial_category_id' => $category->id,
                'title' => 'بدون فرمول', 'amount' => 1000, 'expense_date' => today(), 'payer_responsibility' => 'owner',
            ], $this->createUser());
            $this->fail('Missing formula accepted');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('building_expenses', ['title' => 'بدون فرمول']);
        }
    }
}
