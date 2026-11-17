<?php

namespace Tests\Feature\Financial;

use App\Actions\BuildingExpense\CreateBuildingExpense;
use App\Actions\BuildingExpense\UpdateBuildingExpense;
use App\Models\BuildingExpense;
use App\Models\ChargeFormula;
use App\Models\ChargePeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\UserRoleAssignment;
use App\Services\Charge\BuildingBillScheduleService;
use App\Services\ChargePeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class BuildingBillScheduleTest extends TestCase
{
    use CreatesBuildingDomainData, RefreshDatabase;

    private function periods(int $buildingId, int $count = 5): array
    {
        return array_map(fn ($i) => ChargePeriod::create([
            'building_id' => $buildingId, 'title' => 'دوره '.$i,
            'period_start' => now()->startOfMonth()->addMonths($i)->toDateString(),
            'period_end' => now()->startOfMonth()->addMonths($i)->endOfMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->addMonths($i + 1)->addDays(5)->toDateString(), 'status' => 'draft',
        ]), range(0, $count - 1));
    }

    private function data($graph, $period, int $count = 1, int $amount = 1001): array
    {
        return ['building_id' => $graph['building']->id, 'title' => 'صورتحساب آزمایشی',
            'expense_date' => today()->subYear()->toDateString(), 'distribution_mode' => 'equal',
            'payer_responsibility' => 'unit', 'target_unit_ids' => null,
            'start_charge_period_id' => $period->id, 'period_installment_count' => $count,
            'items' => [['title' => 'هزینه', 'quantity' => 1, 'unit_amount' => $amount]]];
    }

    public function test_selected_units_five_installments_exact_total_and_no_double_count_or_other_building(): void
    {
        $graph = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $actor = $this->createUser();
        $second = Unit::factory()->create(['floor_id' => $graph['floor']->id, 'unit_number' => '102', 'is_active' => true]);
        $excluded = Unit::factory()->create(['floor_id' => $graph['floor']->id, 'unit_number' => '103', 'is_active' => true]);
        $periods = $this->periods($graph['building']->id);
        $expense = app(CreateBuildingExpense::class)->execute([...$this->data($graph, $periods[0], 5), 'target_unit_ids' => [$graph['unit']->id, $second->id]], $actor);
        $this->assertSame(1001, $expense->amount);
        $this->assertSame([200, 200, 200, 200, 201], $expense->periodAllocations()->orderBy('sequence')->pluck('amount')->all());
        $charges = app(ChargePeriodService::class);
        foreach ($periods as $index => $period) {
            $preview = $charges->preview($period);
            $this->assertSame([], $preview['errors']);
            $this->assertSame($index === 4 ? 201 : 200, $preview['expense_total']);
            $charges->calculate($period, $preview['review_token']);
            $charges->calculate($period, $charges->preview($period)['review_token']);
            $charges->issue($period, $actor);
            $this->assertSame(2, $period->unitInvoices()->count());
        }
        $this->assertSame(1001, (int) UnitInvoice::sum('total_amount'));
        $this->assertDatabaseMissing('unit_invoices', ['unit_id' => $excluded->id]);
        $this->assertDatabaseMissing('unit_invoices', ['unit_id' => $other['unit']->id]);
        foreach (['edit', 'delete'] as $action) {
            try {
                if ($action === 'edit') {
                    app(UpdateBuildingExpense::class)->execute($expense, ['title' => 'تغییر'], $actor);
                } else {
                    app(BuildingBillScheduleService::class)->assertEditable($expense);
                }
                $this->fail('Issued document mutable');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_credit_and_quantity_are_preserved_and_formula_applies_only_selected_units(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();
        [$period] = $this->periods($graph['building']->id, 1);
        $category = $graph['building']->financialCategories()->where('default_key', 'energy.3')->firstOrFail();
        $formula = ChargeFormula::create(['building_id' => $graph['building']->id, 'title' => 'آب', 'calculation_type' => 'equal', 'is_active' => true]);
        $formula->chargeItems()->create(['financial_category_id' => $category->id, 'title' => 'آب', 'base_amount' => 0]);
        $expense = app(CreateBuildingExpense::class)->execute([...$this->data($graph, $period),
            'distribution_mode' => 'formula', 'financial_category_id' => $category->id,
            'target_unit_ids' => [$graph['unit']->id],
            'items' => [['title' => 'طلب', 'quantity' => 2, 'unit_amount' => -300], ['title' => 'بدهی', 'quantity' => 1, 'unit_amount' => 100]],
        ], $actor);
        $this->assertSame(-500, $expense->amount);
        $service = app(ChargePeriodService::class);
        $service->calculate($period, $service->preview($period)['review_token']);
        $service->issue($period, $actor);
        $this->assertSame(-500, $period->unitInvoices()->first()->total_amount);
    }

    public function test_closed_or_foreign_or_insufficient_periods_and_foreign_units_rollback(): void
    {
        $graph = $this->createBuildingGraph();
        $foreign = $this->createBuildingGraph();
        $actor = $this->createUser();
        $periods = $this->periods($graph['building']->id, 2);
        [$foreignPeriod] = $this->periods($foreign['building']->id, 1);
        $periods[1]->update(['status' => 'closed']);
        foreach ([['start_charge_period_id' => $foreignPeriod->id], ['start_charge_period_id' => $periods[1]->id], ['period_installment_count' => 2], ['period_installment_count' => 5], ['target_unit_ids' => [$foreign['unit']->id]], ['target_unit_ids' => []]] as $invalid) {
            try {
                app(CreateBuildingExpense::class)->execute([...$this->data($graph, $periods[0]), ...$invalid], $actor);
                $this->fail('Invalid selection accepted');
            } catch (ValidationException) {
                $this->assertSame(0, BuildingExpense::count());
            }
        }
    }

    public function test_api_enforces_scope_closed_periods_and_server_calculated_total(): void
    {
        $graph = $this->createBuildingGraph();
        $foreign = $this->createBuildingGraph();
        $actor = $this->createUser();
        [$period, $closed] = $this->periods($graph['building']->id, 2);
        $closed->update(['status' => 'closed']);
        $role = Role::create(['name' => 'bill-manager', 'display_name' => 'مدیر']);
        foreach (['reports.dashboard.view', 'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete'] as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'module' => explode('.', $name)[0]]));
        }
        UserRoleAssignment::create(['user_id' => $actor->id, 'role_id' => $role->id, 'scope_type' => $graph['building']->getMorphClass(), 'scope_id' => $graph['building']->id, 'is_active' => true]);
        Sanctum::actingAs($actor);
        $this->get('/management/operations/expenses')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('title', 'صورتحساب ساختمان')->has('workspace.periods', 1)->has('workspace.units', 1)
            ->where('workspace.periods.0.id', $period->id)->where('workspace.units.0.id', $graph['unit']->id));
        $this->postJson('/api/v1/expenses', [...$this->data($graph, $period), 'start_charge_period_id' => $closed->id])->assertUnprocessable();
        $this->postJson('/api/v1/expenses', [...$this->data($graph, $period), 'target_unit_ids' => [$foreign['unit']->id]])->assertUnprocessable();
        $this->postJson('/api/v1/expenses', [...$this->data($foreign, $period)])->assertForbidden();
        $created = $this->postJson('/api/v1/expenses', [...$this->data($graph, $period), 'amount' => 999999])->assertCreated()->assertJsonPath('data.amount', 1001);
        $id = $created->json('data.id');
        $this->patchJson('/api/v1/expenses/'.$id, ['start_charge_period_id' => $closed->id, 'period_installment_count' => 1, 'items' => [['title' => 'تغییر', 'quantity' => 1, 'unit_amount' => 1]]])->assertUnprocessable();
        $charges = app(ChargePeriodService::class);
        $charges->calculate($period, $charges->preview($period)['review_token']);
        $charges->issue($period, $actor);
        $this->deleteJson('/api/v1/expenses/'.$id)->assertUnprocessable();
        $this->patchJson('/api/v1/expenses/'.$id, ['title' => 'پس از صدور'])->assertUnprocessable();
    }

    public function test_owner_and_resident_payers_and_all_unit_area_shares_are_preserved(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();
        $owner = $this->createUser();
        $resident = $this->createUser();
        $second = Unit::factory()->create(['floor_id' => $graph['floor']->id, 'unit_number' => '202', 'is_active' => true, 'area' => 300]);
        foreach ([$graph['unit'], $second] as $unit) {
            UnitOwnership::create(['unit_id' => $unit->id, 'user_id' => $owner->id, 'ownership_percentage' => 100, 'starts_at' => today()->subYear(), 'is_primary' => true, 'is_active' => true]);
            UnitOccupancy::create(['unit_id' => $unit->id, 'user_id' => $resident->id, 'occupancy_type' => 'resident', 'starts_at' => today()->subYear(), 'is_primary' => true, 'is_active' => true]);
        }
        [$period] = $this->periods($graph['building']->id, 1);
        $category = $graph['building']->financialCategories()->where('default_key', 'energy.3')->firstOrFail();
        $formula = ChargeFormula::create(['building_id' => $graph['building']->id, 'title' => 'متراژ', 'calculation_type' => 'area', 'is_active' => true]);
        $formula->chargeItems()->create(['financial_category_id' => $category->id, 'title' => 'قبض', 'base_amount' => 0]);
        app(CreateBuildingExpense::class)->execute([...$this->data($graph, $period, 1, 4000), 'distribution_mode' => 'formula', 'financial_category_id' => $category->id, 'payer_responsibility' => 'owner'], $actor);
        app(CreateBuildingExpense::class)->execute([...$this->data($graph, $period, 1, -200), 'payer_responsibility' => 'resident'], $actor);
        $service = app(ChargePeriodService::class);
        $service->calculate($period, $service->preview($period)['review_token']);
        $service->issue($period, $actor);
        $this->assertSame([1000, 3000], UnitInvoice::where('payer_user_id', $owner->id)->orderBy('unit_id')->pluck('subtotal')->all());
        $this->assertSame([900, 2900], UnitInvoice::where('payer_user_id', $owner->id)->orderBy('unit_id')->pluck('outstanding_amount')->all());
        $this->assertSame([-100, -100], UnitInvoice::where('payer_user_id', $resident->id)->orderBy('unit_id')->pluck('total_amount')->all());
    }

    public function test_edit_invalidates_calculated_period_and_old_preview(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();
        [$period] = $this->periods($graph['building']->id, 1);
        $expense = app(CreateBuildingExpense::class)->execute($this->data($graph, $period), $actor);
        $service = app(ChargePeriodService::class);
        $preview = $service->preview($period);
        $service->calculate($period, $preview['review_token']);
        app(UpdateBuildingExpense::class)->execute($expense, ['items' => [['title' => 'اصلاح', 'quantity' => 2, 'unit_amount' => 2000]]], $actor);
        $this->assertSame('draft', $period->fresh()->status->value);
        try {
            $service->calculate($period, $preview['review_token']);
            $this->fail('Stale preview accepted');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $this->assertSame(4000, $service->preview($period)['expense_total']);
    }
}
