<?php

namespace App\Actions\FacilityReservation;

use App\Models\FacilityReservation;
use App\Models\User;
use App\Services\FacilityReservationService;

class ApproveFacilityReservation
{
    public function __construct(private readonly FacilityReservationService $service) {}

    public function execute(FacilityReservation $reservation, User $user): FacilityReservation
    {
        return $this->service->approve($reservation, $user);
    }
}
