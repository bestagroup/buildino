<?php

namespace App\Events;

use App\Models\FacilityReservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FacilityReservationApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly FacilityReservation $reservation) {}
}
