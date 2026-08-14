<?php

namespace App\Actions\UnitOccupancy;

use App\Models\UnitOccupancy;
use App\Models\User;
use App\Services\OccupancyService;

class EndUnitOccupancy
{
    public function __construct(
        private readonly OccupancyService $service
    ) {
    }

    public function execute(
        UnitOccupancy $occupancy,
        User $actor,
        ?string $endsAt = null
    ): UnitOccupancy {
        return $this->service->end(
            $occupancy,
            $actor,
            $endsAt
        );
    }
}
