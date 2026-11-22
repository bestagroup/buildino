<?php

namespace Tests\Feature\Financial;

use App\Services\Charge\OccupancyRegistrationDate;
use App\Services\Charge\PeriodResidentCount;
use App\Services\Charge\ResidentCountHistory;
use App\Services\OccupancyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class OccupancyRegistrationMonthTest extends TestCase
{
    use CreatesBuildingDomainData, RefreshDatabase;

    public function test_initial_occupancy_and_count_start_on_persian_registration_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-14 12:00:00'));
        $graph = $this->createBuildingGraph();
        $occupancy = app(OccupancyService::class)->assign([
            'unit_id' => $graph['unit']->id, 'user_id' => $this->createUser()->id,
            'occupancy_type' => 'owner', 'starts_at' => '2026-08-23', 'resident_count' => 3,
        ]);
        $this->assertSame('2026-10-23', $occupancy->starts_at->toDateString());
        $this->assertDatabaseHas('unit_resident_counts', ['unit_id' => $graph['unit']->id, 'effective_from' => '2026-10-23', 'resident_count' => 3]);
        $counter = app(PeriodResidentCount::class);
        $this->assertSame(0, $counter->count($graph['unit'], CarbonImmutable::parse('2026-09-23'), CarbonImmutable::parse('2026-10-22')));
        $this->assertSame(3, $counter->count($graph['unit'], CarbonImmutable::parse('2026-10-23'), CarbonImmutable::parse('2026-11-21')));
    }

    public function test_repair_is_scoped_previewable_idempotent_and_preserves_later_counts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-14 12:00:00'));
        $graph = $this->createBuildingGraph();
        $unit = $graph['unit'];
        $occupancy = $unit->unitOccupancies()->create(['user_id' => $this->createUser()->id,
            'occupancy_type' => 'tenant', 'starts_at' => '2026-08-23', 'is_active' => true]);
        app(ResidentCountHistory::class)->record($unit, 3, '2026-11-14');
        app(ResidentCountHistory::class)->record($unit, 4, '2026-11-15');
        $this->artisan('occupancies:align-registration-month', ['building' => $graph['building']->id])->assertSuccessful();
        $this->assertSame('2026-08-23', $occupancy->fresh()->starts_at->toDateString());
        foreach ([1, 2] as $attempt) {
            $this->artisan('occupancies:align-registration-month', ['building' => $graph['building']->id, '--apply' => true])->assertSuccessful();
        }
        $this->assertSame('2026-10-23', $occupancy->fresh()->starts_at->toDateString());
        $this->assertDatabaseHas('unit_resident_counts', ['unit_id' => $unit->id, 'effective_from' => '2026-10-23', 'resident_count' => 3]);
        $this->assertDatabaseHas('unit_resident_counts', ['unit_id' => $unit->id, 'effective_from' => '2026-11-15', 'resident_count' => 4]);
        $this->assertSame(1, DB::table('unit_resident_counts')->where('unit_id', $unit->id)->where('effective_from', '2026-10-23')->count());
    }

    public function test_repair_refuses_buildings_with_issued_periods(): void
    {
        $graph = $this->createBuildingGraph();
        \App\Models\ChargePeriod::create([
            'building_id' => $graph['building']->id, 'title' => 'Issued',
            'period_start' => '2026-09-23', 'period_end' => '2026-10-22',
            'due_date' => '2026-10-23', 'status' => 'issued', 'created_by' => $this->createUser()->id,
        ]);
        $this->artisan('occupancies:align-registration-month', ['building' => $graph['building']->id, '--apply' => true])->assertFailed();
    }

    public function test_subsequent_occupancy_keeps_the_explicit_move_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-14 12:00:00'));
        $graph = $this->createBuildingGraph();
        $graph['unit']->unitOccupancies()->create(['user_id' => $this->createUser()->id,
            'occupancy_type' => 'tenant', 'starts_at' => '2026-08-23', 'ends_at' => '2026-10-31', 'is_active' => false]);
        $occupancy = app(OccupancyService::class)->assign([
            'unit_id' => $graph['unit']->id, 'user_id' => $this->createUser()->id,
            'occupancy_type' => 'tenant', 'starts_at' => '2026-11-05', 'resident_count' => 2,
        ]);
        $this->assertSame('2026-11-05', $occupancy->starts_at->toDateString());
    }

    public function test_month_boundary_uses_tehran_timezone(): void
    {
        $this->assertSame('2026-09-23', app(OccupancyRegistrationDate::class)->monthStart(CarbonImmutable::parse('2026-09-22 21:00:00', 'UTC')));
    }
}
