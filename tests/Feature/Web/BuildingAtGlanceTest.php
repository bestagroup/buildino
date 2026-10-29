<?php

namespace Tests\Feature\Web;

use App\Models\Role;
use App\Models\ChargeCalculation;
use App\Models\ChargeFormula;
use App\Models\ChargePeriod;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\UserRoleAssignment;
use App\Services\Web\BuildingAtGlanceService;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class BuildingAtGlanceTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_report_uses_current_people_and_posted_debt_without_spending_wallet_credit(): void
    {
        $graph = $this->createBuildingGraph();
        $unit = $graph['unit'];
        $unit->update(['resident_count' => 4]);
        $old = $this->createUser(['first_name' => 'Former']);
        $current = $this->createUser(['first_name' => 'Current']);
        $unit->unitOwnerships()->create(['user_id' => $old->id, 'is_active' => false, 'starts_at' => now()->subYear(), 'ends_at' => now()->subDay()]);
        $unit->unitOwnerships()->create(['user_id' => $current->id, 'is_active' => true, 'starts_at' => now()->subDay()]);
        $unit->unitOccupancies()->create(['user_id' => $current->id, 'occupancy_type' => 'resident', 'is_active' => true, 'starts_at' => now()->subDay()]);
        $wallet = app(\App\Services\Wallet\WalletService::class)->walletFor($unit, 'IRR');
        $wallet->update(['balance' => 5000, 'locked_balance' => 1000, 'is_active' => true]);
        foreach (['issued' => 6000, 'draft' => 9000, 'cancelled' => 7000, 'void' => 8000] as $status => $amount) {
            UnitInvoice::query()->create([
                'building_id' => $graph['building']->id, 'unit_id' => $unit->id,
                'invoice_number' => 'GLANCE-'.$status, 'issue_date' => now(), 'due_date' => now()->addMonth(),
                'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(),
                'total_amount' => $amount, 'outstanding_amount' => $amount, 'status' => $status,
            ]);
        }
        $report = app(BuildingAtGlanceService::class)->report($graph['building']);
        $row = $report['rows']->first();
        $this->assertSame(4, $report['totals']['people']);
        $this->assertCount(1, $row['owners']);
        $this->assertStringContainsString('Current', $row['owners']->first());
        $this->assertSame(6000, $row['debt']);
        $this->assertSame(4000, $row['available']);
        $this->assertSame(2000, $row['net']);
        $this->assertNull($row['latest']);
        $this->assertSame(5000, $wallet->fresh()->balance);
        $period = \App\Models\ChargePeriod::query()->create([
            'building_id' => $graph['building']->id, 'title' => 'Latest calculation',
            'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(),
            'due_date' => now()->addMonth(), 'status' => 'calculated',
        ]);
        UnitInvoice::query()->where('invoice_number', 'GLANCE-draft')->update(['charge_period_id' => $period->id]);
        $report = app(BuildingAtGlanceService::class)->report($graph['building']);
        $this->assertSame(9000, $report['rows']->first()['latest']);
        $this->assertSame(6000, $report['rows']->first()['debt']);

    }

    public function test_page_is_scoped_to_the_managers_building(): void
    {
        $this->seed(RoleMatrixSeeder::class);
        $own = $this->createBuildingGraph();
        $other = $this->createBuildingGraph();
        $manager = $this->createUser(['mobile_verified_at' => now()]);
        UserRoleAssignment::query()->create([
            'user_id' => $manager->id,
            'role_id' => Role::query()->where('name', 'building_manager')->value('id'),
            'scope_type' => $own['building']->getMorphClass(), 'scope_id' => $own['building']->id, 'is_active' => true,
        ]);
        $this->get('/management/at-a-glance')->assertRedirect();
        $this->actingAs($manager)->get('/management/at-a-glance?building_id='.$own['building']->id)
            ->assertOk()->assertSee('در یک نگاه')->assertViewHas('building', fn ($building) => $building->id === $own['building']->id);
        $this->get('/management/at-a-glance?building_id='.$other['building']->id)->assertForbidden();
    }

    public function test_stale_resident_counts_are_not_counted_and_relationships_use_the_buildings_local_day(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-29 22:30:00', 'UTC'));
        $graph = $this->createBuildingGraph();
        $unit = $graph['unit']; $unit->update(['resident_count' => 4]);
        $reports = app(BuildingAtGlanceService::class);
        $this->assertSame(0, $reports->report($graph['building'])['totals']['people']);
        $resident = $this->createUser();
        $unit->unitOccupancies()->create(['user_id' => $resident->id, 'occupancy_type' => 'resident', 'is_active' => true, 'starts_at' => '2026-10-30']);
        $report = $reports->report($graph['building']);
        $this->assertSame(4, $report['totals']['people']);
        $this->assertSame('2026-10-30', $report['generatedAt']->toDateString());
        $this->travelBack();
    }

    public function test_credit_from_one_unit_does_not_hide_another_units_shortfall(): void
    {
        $graph = $this->createBuildingGraph();
        $otherUnit = Unit::query()->create(['floor_id' => $graph['floor']->id, 'unit_number' => '202', 'area' => 80, 'usage_type' => 'residential', 'is_active' => true]);
        $wallet = app(\App\Services\Wallet\WalletService::class)->walletFor($otherUnit, 'IRR');
        $wallet->update(['balance' => 7000, 'locked_balance' => 0, 'is_active' => true]);
        $this->invoice($graph, ['outstanding_amount' => 7000, 'total_amount' => 7000]);
        $report = app(BuildingAtGlanceService::class)->report($graph['building']);
        $this->assertSame(0, $report['totals']['net']);
        $this->assertSame(7000, $report['totals']['shortfall']);
        $this->assertSame(7000, $report['totals']['surplus']);
        $wallet->update(['is_active' => false]);
        $report = app(BuildingAtGlanceService::class)->report($graph['building']);
        $this->assertSame(0, $report['totals']['available']);
        $this->assertSame(7000, $report['totals']['shortfall']);
    }

    public function test_cancelled_invoices_and_published_periods_never_resurrect_calculation_estimates(): void
    {
        $graph = $this->createBuildingGraph();
        $period = ChargePeriod::query()->create(['building_id' => $graph['building']->id, 'title' => 'دوره نمونه', 'period_start' => today()->startOfMonth(), 'period_end' => today()->endOfMonth(), 'due_date' => today()->addMonth(), 'status' => 'calculated']);
        $formula = ChargeFormula::query()->create(['building_id' => $graph['building']->id, 'title' => 'فرمول نمونه', 'calculation_type' => 'fixed', 'is_active' => true]);
        ChargeCalculation::query()->create(['charge_period_id' => $period->id, 'unit_id' => $graph['unit']->id, 'charge_formula_id' => $formula->id, 'calculated_amount' => 1000, 'calculation_snapshot' => []]);
        $reports = app(BuildingAtGlanceService::class);
        $this->assertSame(1000, $reports->report($graph['building'])['rows']->first()['latest']);
        $invoice = $this->invoice($graph, ['charge_period_id' => $period->id, 'status' => 'void']);
        $row = $reports->report($graph['building'])['rows']->first();
        $this->assertNull($row['latest']);
        $this->assertSame('excluded', $row['latestSource']);
        $period->update(['status' => 'issued']);
        $invoice->delete();
        $this->assertNull($reports->report($graph['building'])['rows']->first()['latest']);
        $draft = $this->invoice($graph, ['charge_period_id' => $period->id, 'status' => 'draft', 'total_amount' => 500]);
        $this->assertNull($reports->report($graph['building'])['rows']->first()['latest']);
        $draft->update(['status' => 'issued', 'outstanding_amount' => 500]);
        $this->assertSame(500, $reports->report($graph['building'])['rows']->first()['latest']);
    }

    public function test_distinct_people_with_identical_names_are_not_merged(): void
    {
        $graph = $this->createBuildingGraph();
        foreach ([1, 2] as $index) {
            $user = $this->createUser(['first_name' => 'علی', 'last_name' => 'رضایی']);
            $graph['unit']->unitOwnerships()->create(['user_id' => $user->id, 'is_active' => true, 'starts_at' => today()->subDay()]);
        }
        $this->assertCount(2, app(BuildingAtGlanceService::class)->report($graph['building'])['rows']->first()['owners']);
    }

    public function test_report_scope_uses_financial_permission_and_links_preserve_building_context(): void
    {
        $dashboard = $this->createBuildingGraph(); $financial = $this->createBuildingGraph(); $user = $this->createUser();
        $this->grant($user, $dashboard['building'], ['reports.dashboard.view']);
        $this->grant($user, $financial['building'], ['reports.financial.view', 'units.view', 'unit-ownerships.view', 'unit-occupancies.view']);
        $response = $this->actingAs($user)->get('/management/at-a-glance?building_id='.$financial['building']->id)->assertOk()
            ->assertViewHas('building', fn ($building) => $building->id === $financial['building']->id)
            ->assertSee('data-glance-page', false)->assertSee('data-glance-unit', false)
            ->assertViewHas('canViewInvoices', false)->assertViewHas('canViewPeriods', false)
            ->assertSee('unit_id='.$financial['unit']->id, false)->assertSee('building_id='.$financial['building']->id, false);
        $this->get('/management/at-a-glance?building_id='.$dashboard['building']->id)->assertForbidden();
    }

    private function invoice(array $graph, array $data = []): UnitInvoice
    {
        return UnitInvoice::query()->create(array_merge(['building_id' => $graph['building']->id, 'unit_id' => $graph['unit']->id, 'invoice_number' => 'GLANCE-'.uniqid(), 'issue_date' => today(), 'due_date' => today()->addMonth(), 'total_amount' => 6000, 'outstanding_amount' => 6000, 'status' => 'issued'], $data));
    }

    private function grant($user, $scope, array $names): void
    {
        $role = Role::query()->create(['name' => 'glance-'.uniqid(), 'display_name' => 'گزارش', 'is_system' => false]);
        $role->permissions()->sync(collect($names)->map(fn ($name) => Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name, 'module' => explode('.', $name)[0]])->id));
        UserRoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => $scope->getMorphClass(), 'scope_id' => $scope->id, 'is_active' => true]);
    }
}
