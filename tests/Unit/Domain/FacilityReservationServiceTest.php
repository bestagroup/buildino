<?php

namespace Tests\Unit\Domain;

use App\Enums\ReservationStatus;
use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\FacilityReservationRule;
use App\Services\FacilityReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class FacilityReservationServiceTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_overlapping_reservation_is_rejected(): void
    {
        $graph = $this->createBuildingGraph();
        $user = $this->createUser();

        $facility = BuildingFacility::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'Pool',
            'code' => 'POOL',
            'type' => 'pool',
            'capacity' => 10,
            'is_active' => true,
        ]);

        FacilityReservation::query()->create([
            'uuid' => (string) str()->uuid(),
            'building_facility_id' => $facility->id,
            'unit_id' => $graph['unit']->id,
            'user_id' => $user->id,
            'reservation_date' => now()->addDay()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '19:00',
            'status' => ReservationStatus::Approved,
        ]);

        $this->expectException(ValidationException::class);

        app(FacilityReservationService::class)->create([
            'building_facility_id' => $facility->id,
            'unit_id' => $graph['unit']->id,
            'user_id' => $user->id,
            'reservation_date' => now()->addDay()->toDateString(),
            'start_time' => '18:30',
            'end_time' => '19:30',
        ]);
    }

    public function test_auto_confirm_rule_approves_reservation(): void
    {
        $graph = $this->createBuildingGraph();
        $user = $this->createUser();

        $facility = BuildingFacility::query()->create([
            'building_id' => $graph['building']->id,
            'title' => 'Gym',
            'code' => 'GYM',
            'type' => 'gym',
            'capacity' => 10,
            'is_active' => true,
        ]);

        FacilityReservationRule::query()->create([
            'building_facility_id' => $facility->id,
            'max_duration_minutes' => 60,
            'max_reservation_per_unit' => 1,
            'cancel_before_minutes' => 60,
            'refund_percentage' => 100,
            'auto_confirm' => true,
        ]);

        $reservation = app(FacilityReservationService::class)->create([
            'building_facility_id' => $facility->id,
            'unit_id' => $graph['unit']->id,
            'user_id' => $user->id,
            'reservation_date' => now()->addDay()->toDateString(),
            'start_time' => '16:00',
            'end_time' => '17:00',
        ]);

        $this->assertSame(ReservationStatus::Approved, $reservation->status);
        $this->assertNotNull($reservation->approved_at);
    }
}
