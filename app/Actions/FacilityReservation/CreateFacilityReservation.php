<?php

namespace App\Actions\FacilityReservation;

use App\Models\FacilityReservation;
use App\Services\FacilityReservationService;

class CreateFacilityReservation
{
    public function __construct(private readonly FacilityReservationService $service) {}

    public function execute(array $data): FacilityReservation
    {
        return $this->service->create($data);
    }
}
