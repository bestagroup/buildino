<?php

namespace Tests\Feature\Web;

use App\Models\Role;
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
}
