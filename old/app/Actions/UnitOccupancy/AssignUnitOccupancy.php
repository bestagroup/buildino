<?php

namespace App\Actions\UnitOccupancy;

use App\Models\UnitOccupancy;
use App\Models\User;
use App\Services\OccupancyService;

class AssignUnitOccupancy
{
    public function __construct(
        private readonly OccupancyService $service
    ) {
    }

    public function execute(
        array $data,
        ?User $actor = null
    ): UnitOccupancy {
        return $this->service->assign(
            $data,
            $actor
        );
    }
}
