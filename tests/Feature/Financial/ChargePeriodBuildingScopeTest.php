<?php

namespace Tests\Feature\Financial;

use App\Models\BuildingExpense;
use App\Models\ChargeFormula;
use App\Models\FinancialCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesChargePeriodScenario;
use Tests\TestCase;

final class ChargePeriodBuildingScopeTest extends TestCase
{
    use CreatesChargePeriodScenario, RefreshDatabase;

    private function buildingsAndManager(): array
    {
        [$own, $manager, , $ownPeriod] = $this->chargePeriodScenario();
        [$other, , , $otherPeriod, $otherFormula] = $this->chargePeriodScenario();
        // Same complex and unit number must never imply the same building scope.
        $other['building']->update(['complex_id' => $own['complex']->id]);
        $other['unit']->update(['unit_number' => $own['unit']->unit_number]);
        $otherFormula->chargeItems()->update(['base_amount' => 900000]);
        $role = Role::create(['name' => 'scoped-charge-manager', 'display_name' => 'Manager']);
        foreach (['create', 'view', 'calculate', 'issue'] as $action) {
            $permission = Permission::firstOrCreate(
                ['name' => 'charge-periods.'.$action],
                ['display_name' => $action, 'module' => 'charge-periods']
            );
            $role->permissions()->attach($permission);
        }
        UserRoleAssignment::create([
            'user_id' => $manager->id, 'role_id' => $role->id,
            'scope_type' => $own['building']->getMorphClass(), 'scope_id' => $own['building']->id,
            'is_active' => true, 'starts_at' => now()->subDay(),
        ]);
        Sanctum::actingAs($manager);

        return [$own, $other, $ownPeriod, $otherPeriod];
    }

    private function periodData(): array
    {
        return ['title' => 'October', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'due_date' => '2026-11-10'];
    }

    public function test_create_preview_calculate_and_issue_use_only_the_period_building(): void
    {
        [$own, $other, $oldPeriod, $otherPeriod] = $this->buildingsAndManager();
        $oldPeriod->delete();
        $second = Unit::create([
            'floor_id' => $own['floor']->id, 'unit_number' => 'SECOND',
            'area' => 80, 'usage_type' => 'residential', 'is_active' => true,
        ]);
        $inactive = Unit::create([
            'floor_id' => $own['floor']->id, 'unit_number' => 'INACTIVE',
            'area' => 80, 'usage_type' => 'residential', 'is_active' => false,
        ]);
        $category = FinancialCategory::create(['title' => 'Shared category', 'type' => 'expense', 'is_active' => true]);
        foreach ([$own, $other] as $graph) {
            $formula = ChargeFormula::create([
                'building_id' => $graph['building']->id, 'title' => 'Equal',
                'calculation_type' => 'equal', 'is_active' => true,
            ]);
            $formula->chargeItems()->create(['title' => 'Equal', 'financial_category_id' => $category->id, 'base_amount' => 0]);
        }
        $bill = BuildingExpense::create([
            'building_id' => $own['building']->id, 'title' => 'Own bill', 'amount' => 300000,
            'financial_category_id' => $category->id, 'expense_date' => '2026-10-10',
            'status' => 'posted', 'payer_responsibility' => 'unit',
        ]);
        $foreignBill = BuildingExpense::create([
            'building_id' => $other['building']->id, 'title' => 'Other bill', 'amount' => 900000,
            'financial_category_id' => $category->id, 'expense_date' => '2026-10-10',
            'status' => 'posted', 'payer_responsibility' => 'unit',
        ]);
        // A forged body building_id must not override the authorized route building.
        $id = $this->postJson('/api/v1/buildings/'.$own['building']->id.'/charge-periods', [
            ...$this->periodData(), 'building_id' => $other['building']->id,
        ])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('charge_periods', ['id' => $id, 'building_id' => $own['building']->id]);
        $preview = $this->getJson("/api/v1/charge-periods/{$id}/preview")
            ->assertOk()->assertJsonPath('data.unit_count', 2)
            ->assertJsonPath('data.expense_total', 300000)->assertJsonPath('data.fixed_total', 200000)
            ->assertJsonCount(1, 'data.expenses')->assertJsonPath('data.expenses.0.id', $bill->id)
            ->json('data');
        $this->postJson("/api/v1/charge-periods/{$id}/calculate", ['review_token' => $preview['review_token']])
            ->assertOk()->assertJsonPath('data.calculations_count', 2)->assertJsonPath('data.expense_allocations_count', 2);
        $this->postJson("/api/v1/charge-periods/{$id}/issue")
            ->assertOk()->assertJsonPath('data.invoices_count', 2);
        foreach ([$own['unit'], $second] as $unit) {
            $this->assertDatabaseHas('charge_calculations', ['charge_period_id' => $id, 'unit_id' => $unit->id, 'calculated_amount' => 100000]);
            $this->assertDatabaseHas('charge_expense_allocations', ['charge_period_id' => $id, 'unit_id' => $unit->id, 'allocated_amount' => 150000]);
            $this->assertDatabaseHas('unit_invoices', ['charge_period_id' => $id, 'building_id' => $own['building']->id, 'unit_id' => $unit->id, 'total_amount' => 250000]);
        }
        foreach ([$other['unit'], $inactive] as $unit) {
            foreach (['charge_calculations', 'charge_expense_allocations', 'unit_invoices'] as $table) {
                $this->assertDatabaseMissing($table, ['unit_id' => $unit->id]);
            }
        }
        $this->assertDatabaseMissing('charge_expense_allocations', ['building_expense_id' => $foreignBill->id]);
        $this->assertDatabaseHas('charge_periods', ['id' => $otherPeriod->id, 'status' => 'draft']);
    }

    public function test_manager_cannot_create_preview_calculate_or_issue_another_buildings_period(): void
    {
        [, $other, , $period] = $this->buildingsAndManager();
        $this->postJson('/api/v1/buildings/'.$other['building']->id.'/charge-periods', $this->periodData())->assertForbidden();
        $this->getJson("/api/v1/charge-periods/{$period->id}/preview")->assertForbidden();
        $this->postJson("/api/v1/charge-periods/{$period->id}/calculate", ['review_token' => str_repeat('a', 64)])->assertForbidden();
        $this->postJson("/api/v1/charge-periods/{$period->id}/issue")->assertForbidden();
        $this->assertDatabaseCount('charge_periods', 2);
        $this->assertDatabaseCount('charge_calculations', 0);
        $this->assertDatabaseCount('charge_expense_allocations', 0);
        $this->assertDatabaseCount('unit_invoices', 0);
    }
}
