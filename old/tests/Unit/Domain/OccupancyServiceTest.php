<?php

namespace Tests\Unit\Domain;

use App\Models\UnitOccupancy;
use App\Services\OccupancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class OccupancyServiceTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_assigning_new_primary_resident_unsets_previous_primary(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();
        $old = $this->createUser();
        $new = $this->createUser();

        $previous = UnitOccupancy::query()->create([
            'unit_id' => $graph['unit']->id,
            'user_id' => $old->id,
            'occupancy_type' => 'tenant',
            'starts_at' => now()->subMonth()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $current = app(OccupancyService::class)->assign([
            'unit_id' => $graph['unit']->id,
            'user_id' => $new->id,
            'occupancy_type' => 'tenant',
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ], $actor);

        $this->assertFalse($previous->fresh()->is_primary);
        $this->assertTrue($current->is_primary);
        $this->assertSame($actor->id, $current->created_by);
    }

    public function test_occupancy_can_be_ended(): void
    {
        $graph = $this->createBuildingGraph();
        $actor = $this->createUser();
        $resident = $this->createUser();

        $occupancy = UnitOccupancy::query()->create([
            'unit_id' => $graph['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' => 'tenant',
            'starts_at' => now()->subMonth()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $result = app(OccupancyService::class)->end($occupancy, $actor);

        $this->assertFalse($result->is_active);
        $this->assertSame($actor->id, $result->ended_by);
        $this->assertNotNull($result->ends_at);
    }
}
